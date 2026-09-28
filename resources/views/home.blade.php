@extends('layouts.app')
@section('title', 'Shop gadgets & repairs')
@section('content')
@foreach($cms['announcements'] ?? [] as $announcement)
<aside class="shell cms-announcement clay-card" aria-label="Store announcement">
    <strong>{{ $announcement['title'] }}</strong>
    @if($announcement['description'])<p>{{ $announcement['description'] }}</p>@endif
</aside>
@endforeach
<section class="hero-section">
    <div class="shell hero-grid">
        <div class="hero-copy">
            <span class="pill">{{ ($cms['hero']['eyebrow'] ?? '') ?: 'Calapan’s local gadget marketplace prototype' }}</span>
            @if(!empty($cms['hero']['headline']))<h1>{{ $cms['hero']['headline'] }}</h1>@else<h1>New tech, second chances, <span>expert care.</span></h1>@endif
            <p>{{ ($cms['hero']['subheadline'] ?? '') ?: 'Shop brand-new, pre-owned and refurbished devices, then request repair or refurbishment service from the same Mobile Arena experience.' }}</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ $cms['hero']['primary_cta_url'] ?? route('products.index') }}">{{ ($cms['hero']['primary_cta_label'] ?? '') ?: 'Browse devices' }}</a>
                <a class="btn btn-secondary" href="{{ $cms['hero']['secondary_cta_url'] ?? route('repair.create') }}">{{ ($cms['hero']['secondary_cta_label'] ?? '') ?: 'Book a repair' }}</a>
            </div>
            <div class="trust-row">
                <div><strong>3</strong><span>clear condition types</span></div>
                <div><strong>1</strong><span>store pickup point</span></div>
                @if(!empty($cms['shop_information']['opening_hours']))<div><strong>Hours</strong><span>See store details below</span></div>
                @else<div><strong>9–9</strong><span>public mall hours</span></div>@endif
            </div>
        </div>
        <div class="hero-art clay-panel" aria-label="Mobile Arena shopping preview">
            @if(!empty($cms['hero']['image']))
                <img class="cms-hero-image" src="{{ $cms['hero']['image'] }}" alt="{{ ($cms['hero']['headline'] ?? '') ?: 'Mobile Arena featured image' }}">
            @else
            <div class="floating-tag tag-one">Brand New</div>
            <div class="floating-tag tag-two">Pre-Owned</div>
            <div class="floating-tag tag-three">Refurbished</div>
            <div class="hero-phone">
                <div class="hero-phone-screen">
                    <span class="mini-logo">MA</span>
                    <small>Mobile Arena</small>
                    <strong>Find your next device</strong>
                    <div class="mini-products"><i></i><i></i><i></i></div>
                </div>
            </div>
            <div class="hero-orb orb-a"></div><div class="hero-orb orb-b"></div>
            @endif
        </div>
    </div>
</section>

@if(!empty($cms['promotions']))
<section class="shell section-block cms-promotions" aria-label="Promotions">
    <div class="section-heading"><div><span class="eyebrow">What’s happening</span><h2>From Mobile Arena</h2></div></div>
    <div class="cms-promo-grid">
        @foreach($cms['promotions'] as $promotion)
        <article class="clay-card cms-promo-card">
            @if($promotion['image'])<img src="{{ $promotion['image'] }}" alt="" loading="lazy">@endif
            <div><h3>{{ $promotion['title'] }}</h3>@if($promotion['description'])<p>{{ $promotion['description'] }}</p>@endif
                @if($promotion['cta_label'] && $promotion['cta_url'])<a class="btn btn-secondary" href="{{ $promotion['cta_url'] }}">{{ $promotion['cta_label'] }}</a>@endif
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif

<section class="shell section-block">
    <div class="section-heading">
        <div><span class="eyebrow">Shop your way</span><h2>Choose by condition</h2></div>
        <a href="{{ route('products.index') }}">See all products →</a>
    </div>
    <div class="condition-grid">
        <a class="condition-tile clay-card new" href="{{ route('products.index', ['condition'=>'brand_new']) }}"><span class="condition-icon">✦</span><div><h3>Brand New</h3><p>Fresh stock with clear product details and availability.</p></div><b>Shop new →</b></a>
        <a class="condition-tile clay-card owned" href="{{ route('products.index', ['condition'=>'pre_owned']) }}"><span class="condition-icon">↻</span><div><h3>Pre-Owned</h3><p>Unit-by-unit grading for more transparent second-hand shopping.</p></div><b>Shop pre-owned →</b></a>
        <a class="condition-tile clay-card refurb" href="{{ route('products.index', ['condition'=>'refurbished']) }}"><span class="condition-icon">✓</span><div><h3>Refurbished</h3><p>Restored units with inspection and service information fields.</p></div><b>Shop refurbished →</b></a>
    </div>
</section>

<section class="shell section-block">
    <div class="section-heading"><div><span class="eyebrow">Browse faster</span><h2>Popular categories</h2></div></div>
    <div class="category-row">
        @foreach($categories as $category)
            <a class="category-chip clay-card" href="{{ route('products.index', ['category'=>$category->slug]) }}">
                <span>{{ strtoupper(substr($category->name,0,2)) }}</span><strong>{{ $category->name }}</strong><small>Explore</small>
            </a>
        @endforeach
    </div>
</section>

@if($featured->isNotEmpty())
<section class="section-tinted">
    <div class="shell section-block">
        <div class="section-heading"><div><span class="eyebrow">Featured demo stock</span><h2>Made for quick comparison</h2></div><a href="{{ route('products.index') }}">View catalog →</a></div>
        <div class="product-grid">@foreach($featured as $product)<x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistIds)" />@endforeach</div>
    </div>
</section>
@endif

<section class="shell service-banner clay-panel">
    <div class="service-copy">
        <span class="pill">Repair & refurbishment</span>
        <h2>{{ ($cms['repair_promo']['heading'] ?? '') ?: 'Your device may not need replacing.' }}</h2>
        <p>{{ ($cms['repair_promo']['description'] ?? '') ?: 'Submit the device, problem and preferred visit date. The prototype stores each request and gives the customer a reference number for follow-up.' }}</p>
        <div class="service-points"><span>Screen & hardware concerns</span><span>Battery / charging issues</span><span>Software diagnostics</span><span>Refurbishment requests</span></div>
        <a class="btn btn-primary" href="{{ $cms['repair_promo']['cta_url'] ?? route('repair.create') }}">{{ ($cms['repair_promo']['cta_label'] ?? '') ?: 'Request service' }}</a>
    </div>
    @if(!empty($cms['repair_promo']['image']))<img class="cms-repair-image" src="{{ $cms['repair_promo']['image'] }}" alt="" loading="lazy">
    @else<div class="repair-visual"><div class="tool-card">Diagnostics<small>Describe the issue</small></div><div class="tool-card">Repair<small>Get store assessment</small></div><div class="tool-card">Refurbish<small>Restore usable units</small></div></div>@endif
</section>

@if($preOwned->isNotEmpty())
<section class="shell section-block">
    <div class="section-heading"><div><span class="eyebrow">Second-life tech</span><h2>Pre-owned & refurbished</h2></div></div>
    <div class="product-grid">@foreach($preOwned as $product)<x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistIds)" />@endforeach</div>
</section>
@endif

@if(!empty($cms['faq']))
<section class="shell section-block cms-faq">
    <div class="section-heading"><div><span class="eyebrow">Helpful answers</span><h2>Frequently asked questions</h2></div></div>
    <div class="cms-faq-grid">@foreach($cms['faq'] as $item)<details class="clay-card"><summary>{{ $item['question'] }}</summary><p>{{ $item['answer'] }}</p></details>@endforeach</div>
</section>
@endif

@if(!empty($cms['shop_information']))
<section class="shell section-block cms-shop-info">
    <div class="section-heading"><div><span class="eyebrow">About the shop</span><h2>Visit Mobile Arena</h2></div></div>
    <div class="cms-shop-grid">
        @if($cms['shop_information']['about'] || $cms['shop_information']['description'])<article class="clay-card"><h3>About us</h3><p>{{ $cms['shop_information']['about'] ?: $cms['shop_information']['description'] }}</p></article>@endif
        @if($cms['shop_information']['opening_hours'])<article class="clay-card"><h3>Opening hours</h3><p>{!! nl2br(e($cms['shop_information']['opening_hours'])) !!}</p></article>@endif
        @if($cms['shop_information']['location_text'] || $cms['shop_information']['contact_information'])<article class="clay-card"><h3>Find and contact us</h3>@if($cms['shop_information']['location_text'])<p>{!! nl2br(e($cms['shop_information']['location_text'])) !!}</p>@endif @if($cms['shop_information']['contact_information'])<p>{!! nl2br(e($cms['shop_information']['contact_information'])) !!}</p>@endif</article>@endif
    </div>
    @if(!empty($cms['shop_information']['social_links']))<div class="cms-social-links">@foreach($cms['shop_information']['social_links'] as $link)<a href="{{ $link['url'] }}" rel="noopener noreferrer" target="_blank">{{ $link['label'] }}</a>@endforeach</div>@endif
</section>
@endif

<section class="shell verified-strip">
    <div><span>✓</span><p><strong>Public business details grounded online</strong><br>Mobile Arena is publicly listed at XentroMall Calapan; exact prototype inventory is intentionally marked as demo data.</p></div>
    <a href="{{ route('products.index') }}">Start shopping →</a>
</section>
@endsection
