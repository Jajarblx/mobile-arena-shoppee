<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderLifecycle;
use App\Services\AuditTrail;
use App\Services\CustomerNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $order->load(['items.product', 'refund', 'statusEvents' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);

        return view('account.order-show', compact('order'));
    }

    public function cancel(Request $request, Order $order, OrderLifecycle $lifecycle)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $lifecycle->transition($order, 'cancelled', 'Cancelled by customer', $request->user());

        return back()->with('success', 'Your order has been cancelled.');
    }

    public function submitPayment(Request $request, Order $order, AuditTrail $audit, CustomerNotifier $notifier)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $data = $request->validate(['payment_reference' => ['required', 'string', 'min:4', 'max:120']]);

        DB::transaction(function () use ($order, $data, $request, $audit, $notifier) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_method !== 'gcash_manual' || $locked->status !== 'pending_payment'
                || ! in_array($locked->payment_status, ['unpaid', 'failed'], true)
                || $locked->stock_state !== 'reserved'
                || $locked->reservation_expires_at?->isPast()) {
                throw ValidationException::withMessages(['payment_reference' => 'This order cannot accept a payment reference now.']);
            }
            $beforePaymentStatus = $locked->payment_status;
            $locked->update([
                'payment_reference' => trim($data['payment_reference']),
                'payment_status' => 'pending_verification',
                'reservation_expires_at' => now()->addMinutes(max(1, (int) config('mobile-arena.inventory.verification_minutes'))),
            ]);
            $locked->statusEvents()->create(['status' => $locked->status, 'note' => 'Payment reference submitted for verification']);
            $audit->record($request->user(), 'payment.reference_submitted', $locked,
                ['payment_status' => $beforePaymentStatus], ['payment_status' => 'pending_verification']);
            $notifier->send($request->user(), 'payment.reference_received', 'Payment reference received',
                $locked->reference.' is awaiting manual verification.', route('account.orders.show', $locked));
            $notifier->admins('admin.payment_reference', 'Payment reference submitted',
                $locked->reference.' is ready for manual verification.', route('admin.orders.show', $locked));
        }, 3);

        return back()->with('success', 'Reference submitted. Mobile Arena will verify it manually.');
    }
}
