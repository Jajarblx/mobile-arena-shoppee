<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\RepairBooking;
use App\Models\Refund;
use App\Services\OrderLifecycle;
use App\Services\InventoryLedger;
use App\Services\AuditTrail;
use App\Services\CustomerNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'low_stock' => Product::whereRaw('stock - reserved_stock <= ?', [(int) config('mobile-arena.inventory.low_stock_threshold')])->count(),
            'orders' => Order::count(),
            'repairs' => RepairBooking::count(),
        ];
        $orders = Order::with('user')->latest()->take(8)->get();
        $repairs = RepairBooking::with('user')->latest()->take(8)->get();
        $products = Product::orderByRaw('(stock - reserved_stock) asc')->orderBy('name')->get();
        $refunds = Refund::with('order')->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'orders', 'repairs', 'products', 'refunds'));
    }

    public function updateOrder(Request $request, Order $order, OrderLifecycle $lifecycle)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUS_LABELS))],
            'note' => ['nullable', 'string', 'max:255'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ]);

        $lifecycle->transition($order, $data['status'], $data['note'] ?? null, $request->user(),
            collect($data)->only(['courier_name', 'tracking_number'])->all());

        return back()->with('success', 'Order status updated.');
    }

    public function updateStock(Request $request, Product $product, InventoryLedger $inventory, AuditTrail $audit)
    {
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'movement_type' => ['required', Rule::in(['adjustment', 'correction', 'damage'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        DB::transaction(function () use ($product, $data, $request, $inventory, $audit) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($data['stock'] < $locked->reserved_stock) {
                throw ValidationException::withMessages(['stock' => 'Physical stock cannot be lower than reserved stock.']);
            }
            if ($data['movement_type'] === 'damage' && $data['stock'] > $locked->stock) {
                throw ValidationException::withMessages(['stock' => 'A damage movement must not increase physical stock.']);
            }
            if ((int) $data['stock'] === (int) $locked->stock) {
                return;
            }
            $before = clone $locked;
            $locked->update(['stock' => $data['stock']]);
            $inventory->record($before, $locked, $data['movement_type'], $request->user(), null, $data['reason']);
            $audit->record($request->user(), 'product.stock_adjusted', $locked,
                ['stock' => $before->stock], ['stock' => $locked->stock], $data['reason']);
        }, 3);

        return back()->with('success', 'Physical stock updated.');
    }

    public function confirmPayment(Request $request, Order $order, OrderLifecycle $lifecycle, AuditTrail $audit, CustomerNotifier $notifier)
    {
        DB::transaction(function () use ($order, $lifecycle, $request, $audit, $notifier) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status === 'paid') {
                return;
            }
            if (in_array($locked->status, ['cancelled', 'completed'], true)
                || ($locked->payment_method === 'gcash_manual' && $locked->reservation_expires_at?->isPast())
                || ($locked->payment_method === 'gcash_manual' && $locked->payment_status !== 'pending_verification')) {
                throw ValidationException::withMessages(['payment_status' => 'This payment is not ready for confirmation.']);
            }
            $before = $locked->payment_status;
            $locked->update(['payment_status' => 'paid', 'payment_confirmed_at' => now()]);
            if ($locked->status === 'pending_payment') {
                $lifecycle->transition($locked, 'processing', 'Payment confirmed by Mobile Arena', $request->user());
            } else {
                $locked->statusEvents()->create(['status' => $locked->status, 'note' => 'Payment confirmed by Mobile Arena']);
            }
            $audit->record($request->user(), 'payment.confirmed', $locked,
                ['payment_status' => $before], ['payment_status' => 'paid']);
            if ($locked->user) {
                $notifier->send($locked->user, 'payment.confirmed', 'Payment confirmed',
                    $locked->reference.' payment was confirmed by Mobile Arena.', route('account.orders.show', $locked));
            }
        }, 3);

        return back()->with('success', 'Payment confirmed.');
    }

    public function rejectPayment(Request $request, Order $order, AuditTrail $audit, CustomerNotifier $notifier)
    {
        DB::transaction(function () use ($order, $request, $audit, $notifier) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_method !== 'gcash_manual' || $locked->payment_status !== 'pending_verification'
                || $locked->status !== 'pending_payment') {
                throw ValidationException::withMessages(['payment_status' => 'This payment confirmation cannot be rejected.']);
            }
            $locked->update([
                'payment_status' => 'failed',
                'reservation_expires_at' => now()->addMinutes(max(1, (int) config('mobile-arena.inventory.reservation_minutes'))),
            ]);
            $locked->statusEvents()->create(['status' => $locked->status, 'note' => 'Payment reference rejected; customer may submit a new one']);
            $audit->record($request->user(), 'payment.rejected', $locked,
                ['payment_status' => 'pending_verification'], ['payment_status' => 'failed']);
            if ($locked->user) {
                $notifier->send($locked->user, 'payment.rejected', 'Payment reference needs correction',
                    $locked->reference.' reference was rejected. You can submit another reference.', route('account.orders.show', $locked));
            }
        }, 3);

        return back()->with('success', 'Payment reference rejected.');
    }

    public function updateRepair(Request $request, RepairBooking $repair, AuditTrail $audit, CustomerNotifier $notifier)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RepairBooking::STATUS_LABELS))],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($repair, $data, $request, $audit, $notifier) {
            $locked = RepairBooking::whereKey($repair->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'estimated_cost', 'technician_notes']);
            $locked->update($data);
            $after = $locked->only(['status', 'estimated_cost', 'technician_notes']);
            if ($before === $after) {
                return;
            }
            $statusChanged = $before['status'] !== $after['status'];
            $locked->statusEvents()->create([
                'status' => $locked->status,
                'note' => $statusChanged ? 'Repair status changed to '.$locked->status_label : 'Repair details updated',
            ]);
            $audit->record($request->user(), $statusChanged ? 'repair.status_changed' : 'repair.details_updated',
                $locked, $before, $after);
            if ($statusChanged && $locked->user && in_array($locked->status, ['confirmed', 'ready_for_pickup', 'completed'], true)) {
                $notifier->send($locked->user, 'repair.'.$locked->status,
                    'Repair '.$locked->status_label, $locked->reference.' is now '.$locked->status_label.'.',
                    route('account.repairs'));
            }
        }, 3);

        return back()->with('success', 'Repair request updated.');
    }
}
