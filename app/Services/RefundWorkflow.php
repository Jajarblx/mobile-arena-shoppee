<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundWorkflow
{
    public function __construct(
        private InventoryLedger $inventory,
        private AuditTrail $audit,
        private CustomerNotifier $notifier,
    ) {}

    public function eligible(Order $order): bool
    {
        return $order->user_id !== null
            && $order->status === 'completed'
            && $order->payment_status === 'paid'
            && $order->completed_at !== null
            && $order->completed_at->gte(now()->subDays(max(1, (int) config('mobile-arena.refund.window_days'))))
            && ! $order->refund()->exists();
    }

    public function request(User $customer, Order $order, string $reason): Refund
    {
        abort_unless($order->user_id === $customer->id, 404);

        return DB::transaction(function () use ($customer, $order, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $this->eligible($locked)) {
                throw ValidationException::withMessages(['refund' => 'This order is not eligible for a refund request.']);
            }
            $refund = $locked->refund()->create([
                'user_id' => $customer->id,
                'amount' => $locked->total,
                'reason' => trim($reason),
                'status' => 'requested',
                'requested_at' => now(),
            ]);
            $this->audit->record($customer, 'refund.requested', $refund, [], ['status' => 'requested', 'amount' => $refund->amount]);
            $this->notifier->send($customer, 'refund.requested', 'Refund request received',
                $locked->reference.' refund request is awaiting review.', route('account.orders.show', $locked));
            $this->notifier->admins('admin.refund_requested', 'New refund request',
                $locked->reference.' has a refund request to review.', route('admin.refunds.index'));

            return $refund;
        }, 3);
    }

    public function review(Refund $refund, User $admin, string $next, ?string $amount, ?string $staffNotes): bool
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($refund, $admin, $next, $amount, $staffNotes) {
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $next) {
                if ($next === 'under_review' && $staffNotes !== $locked->staff_notes) {
                    $beforeNotes = $locked->staff_notes;
                    $locked->update(['staff_notes' => $staffNotes, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
                    $this->audit->record($admin, 'refund.notes_updated', $locked,
                        ['status' => $next, 'staff_notes' => $beforeNotes],
                        ['status' => $next, 'staff_notes' => $staffNotes]);
                    $this->notifier->send($locked->user, 'refund.review_updated', 'Refund review updated',
                        $locked->order->reference.' has a staff update on its refund request.', route('account.orders.show', $locked->order));
                    return true;
                }
                return false;
            }
            if (! in_array($locked->status, ['requested', 'under_review'], true)
                || ! in_array($next, ['under_review', 'approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'This refund status change is not allowed.']);
            }
            $order = $locked->order;
            $approvedAmount = $amount === null ? (float) $locked->amount : (float) $amount;
            if ($next === 'approved' && ($approvedAmount < 0.01 || $approvedAmount > $order->total)) {
                throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero and cannot exceed the order total.']);
            }
            $before = $locked->only(['status', 'amount', 'staff_notes']);
            $locked->fill([
                'status' => $next,
                'staff_notes' => $staffNotes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            if ($next === 'approved') {
                $locked->amount = $approvedAmount;
            }
            $locked->save();
            $this->audit->record($admin, 'refund.'.$next, $locked, $before, $locked->only(['status', 'amount', 'staff_notes']));
            if (in_array($next, ['approved', 'rejected'], true)) {
                $this->notifier->send($locked->user, 'refund.'.$next,
                    $next === 'approved' ? 'Refund approved' : 'Refund request declined',
                    $order->reference.' refund request is '.$next.'.', route('account.orders.show', $order));
            }

            return true;
        }, 3);
    }

    public function complete(Refund $refund, User $admin, string $disposition, ?string $staffNotes): bool
    {
        abort_unless($admin->role === 'admin', 403);

        return DB::transaction(function () use ($refund, $admin, $disposition, $staffNotes) {
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'refunded') {
                return false;
            }
            if ($locked->status !== 'approved' || $order->status !== 'completed'
                || $order->payment_status !== 'paid'
                || ! in_array($disposition, ['sellable', 'damaged', 'not_returned'], true)) {
                throw ValidationException::withMessages(['status' => 'This refund cannot be completed.']);
            }
            if ($disposition === 'sellable' && $order->stock_state !== 'sold') {
                throw ValidationException::withMessages(['return_disposition' => 'Inventory cannot be restored for this legacy or unfulfilled order.']);
            }
            if ($disposition === 'sellable' && (float) $locked->amount !== (float) $order->total) {
                throw ValidationException::withMessages(['return_disposition' => 'Restoring all items requires a full-order refund.']);
            }
            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                if ($disposition === 'not_returned') {
                    break;
                }
                $product = $item->product_id ? Product::whereKey($item->product_id)->lockForUpdate()->first() : null;
                if (! $product) {
                    if ($disposition === 'sellable') {
                        throw ValidationException::withMessages(['return_disposition' => 'A product is missing. Inventory needs manual review.']);
                    }
                    continue;
                }
                if ($disposition === 'sellable') {
                    $before = clone $product;
                    $updated = Product::whereKey($product->id)
                        ->where('stock', '<=', 4294967295 - $item->quantity)
                        ->increment('stock', $item->quantity);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['return_disposition' => 'Inventory could not be restored.']);
                    }
                    $this->inventory->record($before, $product->fresh(), 'refund_return', $admin, $locked, 'Sellable return');
                } else {
                    $this->inventory->record($product, $product, 'damage', $admin, $locked, 'Returned item is damaged');
                }
            }
            $locked->update([
                'status' => 'refunded',
                'staff_notes' => $staffNotes ?? $locked->staff_notes,
                'return_disposition' => $disposition,
                'reviewed_by' => $admin->id,
                'refunded_at' => now(),
                'stock_restored_at' => $disposition === 'sellable' ? now() : null,
            ]);
            $order->update(['payment_status' => 'refunded']);
            $this->audit->record($admin, 'refund.completed', $locked,
                ['status' => 'approved'], $locked->only(['status', 'amount', 'return_disposition', 'stock_restored_at']));
            $this->audit->record($admin, 'payment.refunded', $order,
                ['payment_status' => 'paid'], ['payment_status' => 'refunded']);
            $this->notifier->send($locked->user, 'refund.refunded', 'Refund marked complete',
                $order->reference.' refund was marked complete by Mobile Arena staff. No in-app payment transfer occurred.',
                route('account.orders.show', $order));

            return true;
        }, 3);
    }
}
