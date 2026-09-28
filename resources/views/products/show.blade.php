@extends('layouts.app')
@section('title', $product->name)
@section('content')
<div class="shell breadcrumbs"><a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('products.index') }}">Shop</a><span>›</span><span>{{ $product->name }}</span></div>
<section class="shell product-detail">
    <div class="product-gallery">
        <div class="detail-image clay-panel"><img data-gallery-main src="{{ $product->gallery_urls[0] }}" alt="{{ $product->name }} product image" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"><span class="condition-badge condition-{{ $product->condition }}">{{ $product->condition_label }}</span></div>
        @if(count($product->gallery_urls) > 1)<div class="gallery-thumbnails" aria-label="Product images">@foreach($product->gallery_urls as $index => $url)<button type="button" class="gallery-thumbnail @if($index === 0) active @endif" data-gallery-image="{{ $url }}" data-gallery-alt="{{ $product->name }} image {{ $index + 1 }}" aria-label="View image {{ $index + 1 }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"><img src="{{ $url }}" alt="" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"></button>@endforeach</div>@endif
    </div>
    <div class="detail-copy">
        <p class="eyebrow">{{ $product->brand }} @if($product->model_name) · {{ $product->model_name }} @endif · {{ $product->category->name }}</p>
        <h1>{{ $product->name }}</h1>
        <a class="detail-rating" href="#reviews">@if($product->reviews_count)<span aria-hidden="true">★</span> <strong>{{ number_format((float) $product->average_rating, 1) }}</strong> · {{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }}@else No reviews yet @endif</a>
        <div class="detail-price"><strong>₱{{ number_format((float) $product->price, 0) }}</strong>@if($product->compare_price)<del>₱{{ number_format((float) $product->compare_price, 0) }}</del>@endif</div>
        @if($product->description)<p class="lead">{{ $product->description }}</p>@endif
        <div class="condition-note clay-card"><div><span class="status-dot"></span><strong>{{ $product->condition_label }}@if($product->grade) · {{ $product->grade }}@endif</strong></div>@if($product->warranty_note)<p>{{ $product->warranty_note }}</p>@endif</div>
        <p class="stock-line"><span class="status-dot {{ $product->available_stock ? '' : 'stock-out' }}"></span>{{ $product->stock_label }}@if($product->available_stock) · {{ $product->available_stock }} available @endif</p>
        <form class="buy-form" action="{{ route('cart.store', $product) }}" method="POST">@csrf<label>Quantity<input type="number" name="quantity" min="1" max="{{ max(1, $product->available_stock) }}" value="1" @disabled($product->available_stock < 1)></label><button class="btn btn-primary" type="submit" @disabled($product->available_stock < 1)>Add to cart</button><a class="btn btn-secondary" href="{{ route('cart.index') }}">View cart</a></form>
        @auth
            @if(in_array($product->id, $wishlistIds))<form class="detail-wishlist" method="POST" action="{{ route('wishlist.destroy', $product) }}">@csrf @method('DELETE')<button class="text-btn" type="submit">♥ Saved to wishlist · Remove</button></form>
            @else<form class="detail-wishlist" method="POST" action="{{ route('wishlist.store', $product) }}">@csrf<button class="text-btn" type="submit">♡ Save to wishlist</button></form>@endif
        @else<a class="detail-wishlist text-btn" href="{{ route('login') }}">♡ Sign in to save to wishlist</a>@endauth
    </div>
</section>
<section class="shell detail-lower">
    <div class="spec-card clay-card"><span class="eyebrow">Product details</span><h2>Specifications</h2>@if($product->specifications)<dl>@foreach($product->specifications as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ is_array($value) ? implode(', ', $value) : $value }}</dd></div>@endforeach</dl>@else<p class="muted">Ask the store for additional specifications for this item.</p>@endif</div>
    <div class="buyer-card clay-card"><span class="eyebrow">Know the condition</span><h2>{{ $product->condition_label }} details</h2><p>{{ $product->short_description }}</p>@if($product->warranty_note)<p><strong>Warranty:</strong> {{ $product->warranty_note }}</p>@endif
    @if($product->grade)<p><strong>Cosmetic grade:</strong> {{ $product->grade }}</p>@endif<a href="{{ route('repair.create') }}">Need help with a device? Request repair →</a></div>
</section>
<section class="shell section-block product-reviews" id="reviews">
    <div class="section-heading"><div><span class="eyebrow">Purchased customer feedback</span><h2>Ratings & reviews</h2></div></div>
    <div class="review-layout"><div class="review-summary clay-card"><div class="review-score">@if($product->reviews_count)<strong>{{ number_format((float) $product->average_rating, 1) }}</strong><span aria-hidden="true">★</span>@else<strong>—</strong>@endif</div><p>{{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }}</p>
    @if($product->reviews_count)
        @for($star = 5; $star >= 1; $star--)<div class="rating-bar"><span>{{ $star }} ★</span><meter min="0" max="{{ $product->reviews_count }}" value="{{ $ratingDistribution[$star] ?? 0 }}">{{ $ratingDistribution[$star] ?? 0 }}</meter><small>{{ $ratingDistribution[$star] ?? 0 }}</small></div>@endfor
    @endif</div>
        <div class="review-main">
            @auth
                @if($eligibleItems->isNotEmpty())<div class="review-form-card clay-card"><h3>Write a verified purchase review</h3><form method="POST" action="{{ route('reviews.store', $product) }}">@csrf<label for="review-item">Completed purchase</label><select id="review-item" name="order_item_id" required>@foreach($eligibleItems as $item)<option value="{{ $item->id }}" @selected(old('order_item_id') == $item->id)>Order {{ $item->order->reference }} · {{ $item->order->created_at?->format('M j, Y') }}</option>@endforeach</select><label for="review-rating">Your rating</label><select id="review-rating" name="rating" required><option value="">Select stars</option>@for($star = 5; $star >= 1; $star--)<option value="{{ $star }}" @selected(old('rating') == $star)>{{ $star }} {{ Str::plural('star', $star) }}</option>@endfor</select><label for="review-title">Title <span class="optional">optional</span></label><input id="review-title" name="title" maxlength="120" value="{{ old('title') }}" placeholder="Sum up your experience"><label for="review-body">Your review</label><textarea id="review-body" name="body" rows="4" minlength="10" maxlength="3000" required placeholder="What should another customer know about this product?">{{ old('body') }}</textarea><button class="btn btn-primary" type="submit">Post review</button></form></div>@endif
            @endauth
            @forelse($reviews as $review)<article class="review-card clay-card" id="review-{{ $review->id }}"><div class="review-card-head"><div><strong>{{ $review->display_name }}</strong><span class="verified-badge">Verified purchase</span></div><time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time></div><div class="review-stars" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('☆', 5 - $review->rating) }}</span></div>@if($review->title)<h3>{{ $review->title }}</h3>@endif<p>{{ $review->body }}</p>
                @auth
                    @if($review->user_id === auth()->id())<a class="text-btn" href="{{ route('reviews.edit', $review) }}">Edit your review</a>@endif
                @endauth
            </article>@empty<div class="empty-state clay-card"><h3>No reviews yet</h3><p>Reviews from completed purchases will appear here.</p></div>@endforelse
            {{ $reviews->links() }}
        </div>
    </div>
</section>
<section class="shell section-block"><div class="section-heading"><div><span class="eyebrow">Keep exploring</span><h2>Related products</h2></div></div>@if($related->isNotEmpty())<div class="product-grid">@foreach($related as $item)<x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistIds)" />@endforeach</div>@else<div class="empty-state clay-card"><h3>No related products available</h3><p>Explore the full catalog for more options.</p><a class="btn btn-secondary" href="{{ route('products.index') }}">Browse all products</a></div>@endif</section>
@endsection
