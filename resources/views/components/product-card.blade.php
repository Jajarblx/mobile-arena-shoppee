@props(['product', 'wishlisted' => false])
<article class="product-card clay-card">
    <a class="product-image-wrap" href="{{ route('products.show', $product) }}">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }} product image" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'">
        <span class="condition-badge condition-{{ $product->condition }}">{{ $product->condition_label }}</span>
        @if($product->grade)<span class="grade-badge">{{ $product->grade }}</span>@endif
    </a>
    <div class="product-body">
        <p class="eyebrow">{{ $product->brand }} · {{ $product->category->name }}</p>
        <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
        <p class="product-copy">{{ $product->short_description }}</p>
        <div class="product-rating" aria-label="{{ $product->reviews_count ? number_format((float) $product->average_rating, 1).' out of 5 stars from '.$product->reviews_count.' reviews' : 'No reviews yet' }}">@if($product->reviews_count)<span aria-hidden="true">★</span> {{ number_format((float) $product->average_rating, 1) }} <small>({{ $product->reviews_count }})</small>@else<small>No reviews yet</small>@endif</div>
        <div class="price-line"><strong>₱{{ number_format((float) $product->price, 0) }}</strong>@if($product->compare_price)<del>₱{{ number_format((float) $product->compare_price, 0) }}</del>@endif</div>
        <div class="stock-line"><span class="status-dot {{ $product->available_stock ? '' : 'stock-out' }}"></span>{{ $product->stock_label }}</div>
        <div class="product-card-actions"><form action="{{ route('cart.store', $product) }}" method="POST">@csrf<button class="btn btn-primary btn-full" type="submit" @disabled($product->available_stock < 1)>Add to cart</button></form>@auth<form action="{{ $wishlisted ? route('wishlist.destroy', $product) : route('wishlist.store', $product) }}" method="POST">@csrf @if($wishlisted)@method('DELETE')@endif<button class="wishlist-icon" type="submit" aria-label="{{ $wishlisted ? 'Remove '.$product->name.' from wishlist' : 'Save '.$product->name.' to wishlist' }}" title="{{ $wishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}">{{ $wishlisted ? '♥' : '♡' }}</button></form>@else<a class="wishlist-icon" href="{{ route('login') }}" aria-label="Sign in to save {{ $product->name }} to wishlist" title="Sign in to save">♡</a>@endauth</div>
    </div>
</article>
