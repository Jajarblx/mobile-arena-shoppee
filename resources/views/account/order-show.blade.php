@extends('layouts.app')
@section('title', 'Order '.$order->reference)
@section('content')
<section class="page-hero compact"><div class="shell"><a class="back-link" href="{{ route('account.purchases') }}">← My purchases</a><span class="eyebrow">Order details</span><h1>{{ $order->reference }}</h1><p>Placed {{ $order->created_at->format('M j, Y \a\t g:i A') }}</p></div></section>
<div class="shell order-detail-layout">
    <div class="order-detail-main">
        <section class="clay-card order-detail-card">
            <div class="detail-section-head"><h2>Order status</h2><span class="status-pill">{{ $order->status_label }}</span></div>
            <ol class="status-timeline">
                @forelse($order->statusEvents as $event)
                    <li><span class="timeline-dot" aria-hidden="true"></span><div><strong>{{ $event->note ?: \App\Models\Order::statusLabelFor($event->status) }}</strong><small>{{ $event->created_at->format('M j, Y · g:i A') }}</small><p>{{ \App\Models\Order::statusLabelFor($event->status) }}</p></div></li>
                @empty
                    <li><span class="timeline-dot" aria-hidden="true"></span><div><strong>{{ $order->status_label }}</strong><small>{{ $order->created_at->format('M j, Y · g:i A') }}</small></div></li>
                @endforelse
            </ol>
            @if(app(\App\Services\OrderLifecycle::class)->canCancel($order))
                <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order? This action cannot be undone.')">@csrf<button class="btn btn-secondary" type="submit">Cancel order</button></form>
            @endif
        </section>
        <section class="clay-card order-detail-card">
            <h2>Items in this order</h2>
            @foreach($order->items as $item)
                <div class="purchase-item"><img src="{{ $item->product?->image_url ?? asset('images/products/phone-blue.svg') }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"><div><h3>{{ $item->product_name }}</h3><p>{{ $item->condition }} · Qty {{ $item->quantity }} × ₱{{ number_format((float) $item->unit_price, 2) }}</p></div><strong>₱{{ number_format((float) $item->unit_price * $item->quantity, 2) }}</strong></div>
            @endforeach
        </section>
        @if($order->payment_method === 'gcash_manual')
            <section class="clay-card order-detail-card payment-instructions">
                <span class="eyebrow">Manual e-wallet payment</span><h2>GCash instructions</h2>
                <p>Contact Mobile Arena through its verified store channel for the current payment details. This page does not collect payment or connect to GCash.</p>
                @if($order->status === 'pending_payment' && in_array($order->payment_status, ['unpaid', 'failed']) && $order->stock_state === 'reserved' && $order->reservation_expires_at?->isFuture())
                    @if($order->reservation_expires_at)<p class="payment-deadline">Submit your reference by {{ $order->reservation_expires_at->format('M j, Y · g:i A') }}.</p>@endif
                    <form method="POST" action="{{ route('account.orders.payment-reference', $order) }}" data-submit-once>@csrf<label for="payment_reference">Transaction reference</label><input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" minlength="4" maxlength="120" required><button class="btn btn-primary" type="submit" data-busy-label="Submitting…">Submit for verification</button></form>
                @elseif($order->payment_status === 'pending_verification')
                    <p class="payment-deadline">Reference received. Mobile Arena will verify it manually before preparing your order.@if($order->reservation_expires_at) Verification is due by {{ $order->reservation_expires_at->format('M j, Y · g:i A') }}.@endif</p>
                @elseif($order->status === 'pending_payment' && in_array($order->payment_status, ['unpaid', 'failed']))
                    <p class="payment-deadline">The payment window has ended. Your order will be cancelled when reservation cleanup runs.</p>
                @endif
            </section>
        @endif
        @if($order->refund)
            <section class="clay-card order-detail-card"><span class="eyebrow">Refund request</span><h2>{{ $order->refund->status_label }}</h2><p>{{ $order->refund->reason }}</p><p><strong>Amount: ₱{{ number_format((float) $order->refund->amount, 2) }}</strong></p>@if($order->refund->staff_notes)<p>Staff update: {{ $order->refund->staff_notes }}</p>@endif <a class="btn btn-secondary" href="{{ route('account.refunds') }}">View refunds</a></section>
        @elseif(app(\App\Services\RefundWorkflow::class)->eligible($order))
            <section class="clay-card order-detail-card"><span class="eyebrow">Need help with this order?</span><h2>Request a refund</h2><p>Requests can be submitted within {{ config('mobile-arena.refund.window_days') }} days of completion. Staff will review the request; no money moves through this prototype.</p><form class="refund-request-form" method="POST" action="{{ route('account.refunds.store', $order) }}" data-submit-once>@csrf<label for="refund_reason">Reason for refund</label><textarea id="refund_reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea><button class="btn btn-primary" type="submit" data-busy-label="Submitting…">Submit request</button></form></section>
        @endif
    </div>
    <aside class="order-detail-side">
        <section class="clay-card order-detail-card">
            <h2>Payment summary</h2>
            <div class="mini-line"><span>Items subtotal</span><strong>₱{{ number_format((float) $order->subtotal, 2) }}</strong></div>
            <div class="mini-line"><span>Delivery fee</span><strong>{{ $order->shipping_fee === null ? 'To be confirmed' : '₱'.number_format((float) $order->shipping_fee, 2) }}</strong></div>
            <div class="summary-total"><span>Order total</span><strong>₱{{ number_format($order->total, 2) }}</strong></div>
            @if($order->shipping_fee === null)<p class="microcopy">This legacy order has no confirmed delivery fee.</p>@endif
        </section>
        <section class="clay-card order-detail-card">
            <h2>{{ $order->fulfillment === 'pickup' ? 'Pickup' : 'Delivery' }} details</h2>
            <p><strong>{{ $order->customer_name }}</strong><br>{{ $order->phone }}@if($order->email)<br>{{ $order->email }}@endif</p>
            @if($order->address)<p class="address-snapshot">{{ $order->address }}</p>@else<p>Collect at Mobile Arena, XentroMall Calapan.</p>@endif
            @if($order->fulfillment === 'delivery_request' && ($order->courier_name || $order->tracking_number))
                <h3>Tracking</h3><p>@if($order->courier_name){{ $order->courier_name }}@endif @if($order->tracking_number)<br>Reference: {{ $order->tracking_number }}@endif</p>
            @endif
            <h3>Payment method</h3><p>{{ $order->payment_method_label }}</p>
            <h3>Payment status</h3><p><span class="status-pill">{{ $order->payment_status_label }}</span></p>
            @if($order->payment_reference)<h3>Reference submitted</h3><p>{{ $order->payment_reference }}</p>@endif
            @if($order->notes)<h3>Order notes</h3><p>{{ $order->notes }}</p>@endif
        </section>
    </aside>
</div>
@endsection
