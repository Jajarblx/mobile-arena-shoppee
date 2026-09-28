@extends('layouts.app')
@section('title', 'Cart')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">Your cart</span><h1>Review your items</h1><p>Check condition, quantity and price before continuing.</p></div></section>
<div class="shell cart-layout">
    <section>
        @if($items->isEmpty())
            <div class="empty-state clay-card"><h2>Your cart is empty</h2><p>Browse brand-new, pre-owned and refurbished demo listings.</p><a class="btn btn-primary" href="{{ route('products.index') }}">Start shopping</a></div>
        @else
            <div class="cart-list">
                @foreach($items as $item)
                    <article class="cart-item clay-card">
                        <img src="{{ $item['product']->image_url }}" alt="Reference product photo of {{ $item['product']->name }}" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'">
                        <div class="cart-main">
                            <span class="condition-badge condition-{{ $item['product']->condition }}">{{ $item['product']->condition_label }}</span>
                            <h2><a href="{{ route('products.show', $item['product']) }}">{{ $item['product']->name }}</a></h2>
                            <p>{{ $item['product']->brand }} · {{ $item['product']->sku }}</p>
                            <strong>₱{{ number_format((float) $item['product']->price, 0) }}</strong>
                            @if($item['stock_issue'])<p class="stock-warning" role="alert">Only {{ $item['product']->available_stock }} available now. Update the quantity or remove this item.</p>
                            @else<p class="stock-line">{{ $item['product']->stock_label }}</p>@endif
                        </div>
                        <div class="cart-actions">
                            <form method="POST" action="{{ route('cart.update', $item['product']) }}">@csrf @method('PATCH')<label>Qty<input type="number" name="quantity" min="0" max="{{ $item['product']->available_stock }}" value="{{ $item['quantity'] }}"></label><button class="text-btn">Update</button></form>
                            <form method="POST" action="{{ route('cart.destroy', $item['product']) }}">@csrf @method('DELETE')<button class="text-btn danger">Remove</button></form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
    <aside class="order-summary clay-card">
        <span class="eyebrow">Order summary</span><h2>Subtotal</h2>
        <div class="summary-total"><span>{{ $items->sum('quantity') }} item(s)</span><strong>₱{{ number_format($subtotal, 0) }}</strong></div>
        <p>Delivery is calculated from your address at checkout.</p>
        @if($items->isNotEmpty())
            @if($items->contains(fn ($item) => $item['stock_issue']))<button class="btn btn-primary btn-full" disabled>Update cart to continue</button>
            @else<a class="btn btn-primary btn-full" href="{{ route('checkout.create') }}">Continue to checkout</a>@endif
        @endif
        <a class="btn btn-secondary btn-full" href="{{ route('products.index') }}">Continue shopping</a>
    </aside>
</div>
@endsection
