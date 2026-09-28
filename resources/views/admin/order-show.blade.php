@extends('layouts.app')
@section('title', 'Staff order '.$order->reference)
@section('content')
<section class="page-hero compact"><div class="shell"><a class="back-link" href="{{ route('admin.dashboard') }}">← Operations dashboard</a><span class="eyebrow">Staff fulfillment</span><h1>{{ $order->reference }}</h1><p>{{ $order->customer_name }} · {{ $order->status_label }} · Placed {{ $order->created_at->format('M j, Y') }}</p></div></section>
<div class="shell order-detail-layout">
    <div class="order-detail-main">
        <section class="clay-card order-detail-card"><h2>Customer and fulfillment</h2><p><strong>{{ $order->customer_name }}</strong><br>{{ $order->email }}<br>{{ $order->phone }}</p>
            <p>{{ $order->fulfillment === 'pickup' ? 'Store pickup' : 'Delivery' }}</p>
            @if($order->address)<p class="address-snapshot">{{ $order->address }}</p>@endif
            @if($order->notes)<h3>Customer notes</h3><p>{{ $order->notes }}</p>@endif
        </section>
        <section class="clay-card order-detail-card"><h2>Items and inventory</h2>
            @foreach($order->items as $item)<div class="purchase-item"><img src="{{ $item->product?->image_url ?? asset('images/products/phone-blue.svg') }}" alt="" loading="lazy"><div><h3>{{ $item->product_name }}</h3><p>Qty {{ $item->quantity }} × ₱{{ number_format((float) $item->unit_price, 2) }}</p>@if($item->product)<p>Physical {{ $item->product->stock }} · Reserved {{ $item->product->reserved_stock }} · Available {{ $item->product->available_stock }}</p>@else<p>Product no longer in catalog</p>@endif</div><strong>₱{{ number_format((float) $item->unit_price * $item->quantity, 2) }}</strong></div>@endforeach
            <div class="mini-line"><span>Subtotal</span><strong>₱{{ number_format((float) $order->subtotal, 2) }}</strong></div><div class="mini-line"><span>Delivery</span><strong>₱{{ number_format((float) $order->shipping_fee, 2) }}</strong></div><div class="summary-total"><span>Total</span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
        </section>
        <section class="clay-card order-detail-card"><h2>Order timeline</h2><ol class="status-timeline">@forelse($order->statusEvents as $event)<li><span class="timeline-dot" aria-hidden="true"></span><div><strong>{{ $event->note ?: \App\Models\Order::statusLabelFor($event->status) }}</strong><small>{{ $event->created_at->format('M j, Y · g:i A') }}</small><p>{{ \App\Models\Order::statusLabelFor($event->status) }}</p></div></li>@empty<li><span class="timeline-dot" aria-hidden="true"></span><div><strong>{{ $order->status_label }}</strong></div></li>@endforelse</ol></section>
    </div>
    <aside class="order-detail-side">
        <section class="clay-card order-detail-card"><h2>Current state</h2><p><strong>Order:</strong> {{ $order->status_label }}<br><strong>Reservation:</strong> {{ ucfirst($order->stock_state) }}<br><strong>Payment:</strong> {{ $order->payment_method_label }} · {{ $order->payment_status_label }}</p>@if($order->payment_reference)<p>Submitted reference: {{ $order->payment_reference }}</p>@endif
            @if($order->payment_status !== 'paid' && ! in_array($order->status, ['cancelled', 'completed']) && ($order->payment_method !== 'gcash_manual' || $order->payment_status === 'pending_verification'))
                <form method="POST" action="{{ route('admin.orders.confirm-payment', $order) }}">@csrf<button class="btn btn-primary btn-full" type="submit">Confirm payment manually</button></form>
            @endif
            @if($order->payment_method === 'gcash_manual' && $order->payment_status === 'pending_verification' && $order->status === 'pending_payment')
                <form method="POST" action="{{ route('admin.orders.reject-payment', $order) }}">@csrf<button class="btn btn-secondary btn-full" type="submit">Reject reference</button></form>
            @endif
        </section>
        <section class="clay-card order-detail-card"><h2>Next fulfillment action</h2>
            @php($nextStatuses = app(\App\Services\OrderLifecycle::class)->availableTransitions($order))
            @if($nextStatuses)
                <form class="staff-action-form" method="POST" action="{{ route('admin.orders.update', $order) }}">@csrf @method('PATCH')
                    <label for="staff_status">New status</label><select id="staff_status" name="status">@foreach($nextStatuses as $next)<option value="{{ $next }}">{{ \App\Models\Order::statusLabelFor($next) }}</option>@endforeach</select>
                    @if($order->fulfillment === 'delivery_request')
                        <label for="courier_name">Courier (when shipping)</label><input id="courier_name" name="courier_name" maxlength="120" value="{{ old('courier_name', $order->courier_name) }}">
                        <label for="tracking_number">Tracking reference (when shipping)</label><input id="tracking_number" name="tracking_number" maxlength="120" value="{{ old('tracking_number', $order->tracking_number) }}">
                    @endif
                    <label for="status_note">Staff status note (optional)</label><input id="status_note" name="note" maxlength="255"><button class="btn btn-primary btn-full" type="submit">Update order</button>
                </form>
            @else<p>No normal status action is available for this order.</p>@endif
            @if($order->fulfillment === 'delivery_request' && in_array($order->status, ['shipped', 'completed']))
                <form class="staff-action-form" method="POST" action="{{ route('admin.orders.tracking', $order) }}">@csrf @method('PATCH')<h3>Update tracking</h3><label for="tracking_courier">Courier</label><input id="tracking_courier" name="courier_name" value="{{ $order->courier_name }}" maxlength="120"><label for="tracking_reference">Tracking reference</label><input id="tracking_reference" name="tracking_number" value="{{ $order->tracking_number }}" maxlength="120"><button class="btn btn-secondary btn-full" type="submit">Save tracking</button></form>
            @endif
        </section>
        @if($order->refund)<section class="clay-card order-detail-card"><h2>Refund</h2><p>{{ $order->refund->status_label }} · ₱{{ number_format((float) $order->refund->amount, 2) }}</p><a class="btn btn-secondary" href="{{ route('admin.refunds.index') }}">Review refunds</a></section>@endif
    </aside>
</div>
@endsection
