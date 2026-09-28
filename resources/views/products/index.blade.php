@extends('layouts.app')
@section('title', 'Shop')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">Mobile Arena catalog</span><h1>Find your next device</h1><p>Explore available phones, tablets and accessories with clear condition, price and stock details.</p></div></section>
<div class="shell catalog-layout">
    <aside class="filter-card clay-card" id="catalog-filters">
        <div class="filter-head"><h2>Filters</h2><a href="{{ route('products.index') }}">Reset</a></div>
        <form method="GET" action="{{ route('products.index') }}">
            <label for="catalog-search">Search products</label><input id="catalog-search" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Name, brand, model or category">
            @if(count($conditions))<label for="catalog-condition">Condition</label><select id="catalog-condition" name="condition"><option value="">All conditions</option>@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($filters['condition'] ?? '') === $condition)>{{ match($condition) {'brand_new'=>'Brand New','pre_owned'=>'Pre-Owned','refurbished'=>'Refurbished',default=>ucwords(str_replace('_',' ',$condition))} }}</option>@endforeach</select>@endif
            @if($categories->isNotEmpty())<label for="catalog-category">Category</label><select id="catalog-category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select>@endif
            @if($brands->isNotEmpty())<label for="catalog-brand">Brand</label><select id="catalog-brand" name="brand"><option value="">All brands</option>@foreach($brands as $brand)<option value="{{ $brand }}" @selected(($filters['brand'] ?? '') === $brand)>{{ $brand }}</option>@endforeach</select>@endif
            <div class="price-filter"><label for="min-price">Minimum price (₱)<input id="min-price" name="min_price" type="number" min="0" step="0.01" value="{{ $filters['min_price'] ?? '' }}"></label><label for="max-price">Maximum price (₱)<input id="max-price" name="max_price" type="number" min="0" step="0.01" value="{{ $filters['max_price'] ?? '' }}"></label></div>
            <label for="catalog-rating">Customer rating</label><select id="catalog-rating" name="min_rating"><option value="">Any rating</option><option value="4" @selected(($filters['min_rating'] ?? '') == '4')>4 stars & up</option><option value="3" @selected(($filters['min_rating'] ?? '') == '3')>3 stars & up</option></select>
            <label class="catalog-check"><input type="checkbox" name="in_stock" value="1" @checked(($filters['in_stock'] ?? '') == '1')> In stock only</label>
            <label for="catalog-sort">Sort by</label><select id="catalog-sort" name="sort"><option value="relevance">Featured / default</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price: low to high</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price: high to low</option><option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Highest rated</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name A–Z</option></select>
            <button class="btn btn-primary btn-full" type="submit">Show products</button>
        </form>
    </aside>
    <section class="catalog-results" aria-live="polite">
        <div class="result-bar"><strong>{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</strong><button class="btn btn-secondary filter-toggle" type="button" data-filter-toggle aria-controls="catalog-filters" aria-expanded="false">Filters & sort</button></div>
        @if($products->isEmpty())<div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">◇</span><h2>No matching products</h2><p>Try another search or adjust your filters to explore more devices.</p><a class="btn btn-primary" href="{{ route('products.index') }}">View all products</a></div>@else
        <div class="product-grid">@foreach($products as $product)<x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistIds)" />@endforeach</div>
        <div class="pagination">{{ $products->links('components.pagination') }}</div>@endif
    </section>
</div>
@endsection
