<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderLifecycle
{
    public function __construct(
        private InventoryLedger $inventory,
        private AuditTrail $audit,
        private CustomerNotifier $notifier,
    ) {}

    private const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'pending_payment' => ['processing', 'cancelled'],
        'processing' => ['to_ship', 'completed', 'cancelled'],
        'to_ship' => ['shipped'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function allowedNext(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function canCancel(Order $order): bool
    {
        return in_array('cancelled', $this->availableTransitions($order), true);
    }

    public function availableTransitions(Order $order): array
    {
        return array_values(array_filter($this->allowedNext($order->status), function (string $next) use ($order) {
            if ($next === 'cancelled' && $order->payment_status === 'paid') {
                return false;
            }
            if ($next === 'processing' && $order->payment_method === 'gcash_manual' && $order->payment_status !== 'paid') {
                return false;
            }
            if ($next === 'completed' && ($order->payment_status !== 'paid' || ($order->status === 'processing' && $order->fulfillment !== 'pickup'))) {
                return false;
            }
            if (in_array($next, ['to_ship', 'shipped'], true) && $order->fulfillment === 'pickup') {
                return false;
            }

            return true;
        }));
    }

    public function transition(Order $order, string $next, ?string $note = null, ?User $actor = null, array $details = []): bool
    {
        return DB::transaction(function () use ($order, $next, $note, $actor, $details) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $next) {
                return false;
            }
            if (! in_array($next, $this->allowedNext($locked->status), true)) {
                throw ValidationException::withMessages(['status' => 'This order status change is not allowed.']);
            }
            if ($next === 'cancelled' && $locked->payment_status === 'paid') {
                throw ValidationException::withMessages(['status' => 'Paid orders require a manual refund review before cancellation.']);
            }
            if ($next === 'completed' && $locked->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'Confirm payment before completing this order.']);
            }
            if ($next === 'completed' && $locked->status === 'processing' && $locked->fulfillment !== 'pickup') {
                throw ValidationException::withMessages(['status' => 'Delivery orders must be shipped before completion.']);
            }
            if ($next === 'processing' && $locked->payment_method === 'gcash_manual' && $locked->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'Confirm the manual payment before processing this order.']);
            }
            if (in_array($next, ['to_ship', 'shipped'], true) && $locked->fulfillment === 'pickup') {
                throw ValidationException::withMessages(['status' => 'Pickup orders do not use shipping statuses.']);
            }
            if ($locked->fulfillment === 'pickup' && (($details['courier_name'] ?? null) || ($details['tracking_number'] ?? null))) {
                throw ValidationException::withMessages(['tracking_number' => 'Pickup orders cannot have courier details.']);
            }

            $before = $locked->only(['status', 'stock_state', 'courier_name', 'tracking_number', 'shipped_at', 'completed_at']);

            if ($locked->stock_state === 'reserved' && $next === 'cancelled') {
                $this->moveStock($locked, false, $actor);
                $locked->stock_state = 'released';
            } elseif ($locked->stock_state === 'reserved' && $next === 'completed') {
                $this->moveStock($locked, true, $actor);
                $locked->stock_state = 'sold';
            }

            $locked->status = $next;
            $locked->reservation_expires_at = null;
            if ($next === 'shipped') {
                $locked->shipped_at = now();
                $locked->courier_name = $details['courier_name'] ?? null;
                $locked->tracking_number = $details['tracking_number'] ?? null;
            }
            if ($next === 'completed') {
                $locked->completed_at = now();
            }
            $locked->save();
            $label = $note ?? match ($next) {
                'processing' => 'Preparing order',
                'to_ship' => 'Ready to ship',
                'shipped' => 'Shipped',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                default => 'Order status updated',
            };
            $locked->statusEvents()->create(['status' => $next, 'note' => $label]);
            $action = $actor?->role === 'admin' ? 'order.status_changed'
                : ($actor ? 'order.customer_cancelled' : 'order.reservation_expired');
            $this->audit->record($actor, $action, $locked, $before,
                $locked->only(['status', 'stock_state', 'courier_name', 'tracking_number', 'shipped_at', 'completed_at']), $label);
            if ($locked->user) {
                $title = match ($next) {
                    'processing' => 'Your order is being prepared',
                    'to_ship' => 'Your order is ready to ship',
                    'shipped' => 'Your order has shipped',
                    'completed' => 'Your order is complete',
                    'cancelled' => 'Your order was cancelled',
                    default => 'Order update',
                };
                $this->notifier->send($locked->user, 'order.'.$next, $title,
                    $locked->reference.' · '.$label, route('account.orders.show', $locked));
            }

            return true;
        }, 3);
    }

    public function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_payment' || $locked->stock_state !== 'reserved'
                || ! in_array($locked->payment_status, ['unpaid', 'failed', 'pending_verification'], true)
                || ! $locked->reservation_expires_at?->isPast()) {
                return false;
            }

            return $this->transition($locked, 'cancelled', $locked->payment_status === 'pending_verification'
                ? 'Payment verification window expired; reservation released'
                : 'Payment window expired; reservation released');
        }, 3);
    }

    private function moveStock(Order $order, bool $sold, ?User $actor): void
    {
        foreach ($order->items()->orderBy('product_id')->get() as $item) {
            if ($item->product_id === null) {
                continue;
            }
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product || $product->reserved_stock < $item->quantity || ($sold && $product->stock < $item->quantity)) {
                throw ValidationException::withMessages(['status' => 'Inventory needs review before this order can be updated.']);
            }
            $changes = ['reserved_stock' => DB::raw('reserved_stock - '.(int) $item->quantity)];
            if ($sold) {
                $changes['stock'] = DB::raw('stock - '.(int) $item->quantity);
            }
            $updated = Product::whereKey($product->id)
                ->where('reserved_stock', '>=', $item->quantity)
                ->when($sold, fn ($query) => $query->where('stock', '>=', $item->quantity))
                ->update($changes);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['status' => 'Inventory changed. Please try again.']);
            }
            $this->inventory->record($product, $product->fresh(), $sold ? 'sale' : 'reservation_release',
                $actor, $order, $sold ? 'Order completed' : 'Order cancelled');
        }
    }
}
