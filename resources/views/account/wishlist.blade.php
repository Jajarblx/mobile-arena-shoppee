@extends('layouts.app')
@section('title', 'Wishlist')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">My account</span><h1>Wishlist</h1><p>Your saved products, ready when you are.</p></div></section>
<div class="shell account-layout">@include('account.partials.nav')<div class="account-content">
    @if($products->isEmpty())<div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">♡</span><h2>Your wishlist is empty</h2><p>Save products while browsing to find them here later.</p><a class="btn btn-primary" href="{{ route('products.index') }}">Discover products</a></div>
    @else<div class="wishlist-grid">@foreach($products as $product)
        @if($product->active)<x-product-card :product="$product" :wishlisted="true" />
        @else<div class="product-card clay-card unavailable-product"><div class="product-image-wrap"><img src="{{ $product->image_url }}" alt="{{ $product->name }} product image" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"></div><div class="product-body"><span class="eyebrow">Currently unavailable</span><h3>{{ $product->name }}</h3><p class="product-copy">This item is no longer listed for sale. Your saved item remains here until you remove it.</p><form method="POST" action="{{ route('wishlist.destroy', $product) }}">@csrf @method('DELETE')<button class="btn btn-secondary btn-full" type="submit">Remove from wishlist</button></form></div></div>@endif
    @endforeach</div>{{ $products->links() }}@endif
</div></div>
@endsection
