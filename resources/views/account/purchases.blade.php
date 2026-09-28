@extends('layouts.app')
@section('title', 'My purchases')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">My account</span><h1>My purchases</h1><p>Track each order and review products after they arrive.</p></div></section>
<div class="shell account-layout">@include('account.partials.nav')<div class="account-content">
    <nav class="purchase-tabs" aria-label="Purchase status filters">@foreach($filters as $key => $filter)<a href="{{ route('account.purchases', $key === 'all' ? [] : ['status' => $key]) }}" @class(['active' => $activeFilter === $key]) @if($activeFilter === $key) aria-current="page" @endif>{{ $filter['label'] }}</a>@endforeach</nav>
    @forelse($orders as $order)
        <article class="purchase-card clay-card"><div class="purchase-head"><div><span class="eyebrow">Order {{ $order->reference }}</span><p>Placed {{ $order->created_at->format('M j, Y') }} · {{ $order->fulfillment === 'pickup' ? 'Store pickup' : 'Delivery request' }}</p></div><span class="status-pill">{{ $order->status_label }}</span></div>
        @foreach($order->items as $item)
            <div class="purchase-item"><img src="{{ $item->product?->image_url ?? asset('images/products/phone-blue.svg') }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"><div><h2>{{ $item->product_name }}</h2><p>{{ $item->condition }} · Quantity {{ $item->quantity }}</p>
                @if($order->status === 'completed' && $item->product?->active)
                    @if($item->review)<a class="purchase-review-link" href="{{ route('products.show', $item->product) }}#review-{{ $item->review->id }}">View review</a><a class="purchase-review-link" href="{{ route('reviews.edit', $item->review) }}">Edit review</a>
                    @else<a class="purchase-review-link" href="{{ route('products.show', $item->product) }}#reviews">Rate product</a>@endif
                @endif
            </div><strong>₱{{ number_format((float) $item->unit_price * $item->quantity, 2) }}</strong></div>
        @endforeach
        <div class="purchase-footer"><span>Total <strong>₱{{ number_format($order->total, 2) }}</strong>@if($order->fulfillment === 'delivery_request' && $order->shipping_fee === null)<small>Delivery fee to be confirmed</small>@endif</span><a class="btn btn-secondary" href="{{ route('account.orders.show', $order) }}">View order</a></div></article>
    @empty<div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">◇</span><h2>{{ $activeFilter === 'all' ? 'No purchases yet' : 'No orders in this status' }}</h2><p>{{ $activeFilter === 'all' ? 'Your orders will appear here after checkout.' : 'Try another status tab to find your order.' }}</p><a class="btn btn-primary" href="{{ $activeFilter === 'all' ? route('products.index') : route('account.purchases') }}">{{ $activeFilter === 'all' ? 'Explore products' : 'View all purchases' }}</a></div>@endforelse
    {{ $orders->links() }}
</div></div>
@endsection
