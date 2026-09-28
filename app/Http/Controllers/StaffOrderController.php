<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\AuditTrail;
use App\Services\CustomerNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffOrderController extends Controller
{
    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'statusEvents' => fn ($query) => $query->orderBy('created_at')->orderBy('id'), 'refund']);

        return view('admin.order-show', compact('order'));
    }

    public function updateTracking(Request $request, Order $order, AuditTrail $audit, CustomerNotifier $notifier)
    {
        $data = $request->validate([
            'courier_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ]);
        DB::transaction(function () use ($request, $order, $data, $audit, $notifier) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->fulfillment !== 'delivery_request' || ! in_array($locked->status, ['shipped', 'completed'], true)) {
                throw ValidationException::withMessages(['tracking_number' => 'Tracking can be updated only for shipped delivery orders.']);
            }
            $before = $locked->only(['courier_name', 'tracking_number']);
            $after = [
                'courier_name' => $data['courier_name'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
            ];
            if ($before === $after) {
                return;
            }
            $locked->update($after);
            $locked->statusEvents()->create(['status' => $locked->status, 'note' => 'Tracking details updated']);
            $audit->record($request->user(), 'order.tracking_updated', $locked, $before, $after);
            if ($locked->user) {
                $notifier->send($locked->user, 'order.tracking_updated', 'Tracking details updated',
                    $locked->reference.' tracking details are available.', route('account.orders.show', $locked));
            }
        }, 3);

        return back()->with('success', 'Tracking details updated.');
    }
}
