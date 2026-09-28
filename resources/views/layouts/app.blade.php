<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eef3ff">
    <meta name="description" content="Mobile Arena prototype storefront for XentroMall Calapan — brand-new, pre-owned and refurbished gadgets plus repair service requests.">
    <title>@yield('title', 'Mobile Arena') · Calapan</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
    <div class="prototype-ribbon">
        <span>Prototype</span>
        <p>Inventory, prices and policies are demo data until confirmed by Mobile Arena staff.</p>
    </div>

    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="Mobile Arena home">
                <span class="brand-mark" aria-hidden="true"><span></span></span>
                <span><strong>Mobile Arena</strong><small>XentroMall Calapan</small></span>
            </a>

            <form class="header-search" action="{{ route('products.index') }}" method="GET" role="search">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m21 21-4.4-4.4m2.4-5.6a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input name="q" value="{{ request('q') }}" placeholder="Search phones, tablets, accessories..." aria-label="Search products">
                <button type="submit">Search</button>
            </form>

            <nav class="desktop-nav" aria-label="Main navigation">
                <a href="{{ route('products.index') }}">Shop</a>
                <a href="{{ route('repair.create') }}">Repairs</a>
                <a class="cart-link" href="{{ route('cart.index') }}" aria-label="Cart">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 4h2l2 11h10l2-8H6m3 12a1 1 0 1 0 0 .01M17 19a1 1 0 1 0 0 .01"/></svg>
                    Cart <span>{{ \App\Http\Controllers\CartController::count() }}</span>
                </a>
            </nav>
            <div class="header-account">
                @guest
                    <a class="header-login" href="{{ route('login') }}">Sign in</a>
                    <a class="header-register" href="{{ route('register') }}">Register</a>
                @else
                    <a class="header-notification" href="{{ route('account.notifications') }}" aria-label="Notifications, {{ auth()->user()->unreadNotifications()->count() }} unread"><svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>@if(auth()->user()->unreadNotifications()->count())<b>{{ auth()->user()->unreadNotifications()->count() }}</b>@endif</a>
                    <details class="account-menu"><summary><span class="nav-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="nav-name">{{ auth()->user()->name }}</span><span aria-hidden="true">⌄</span></summary><div class="account-dropdown"><a href="{{ route('account.index') }}">My account</a><a href="{{ route('account.purchases') }}">My purchases</a><a href="{{ route('account.repairs') }}">Repair requests</a><a href="{{ route('account.wishlist') }}">Wishlist</a>@if(auth()->user()->role === 'admin')<a href="{{ route('admin.dashboard') }}">Admin dashboard</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div></details>
                @endguest
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="shell toast" role="status">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="shell form-errors" role="alert">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="shell form-errors" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <main>@yield('content')</main>

    <footer class="site-footer">
        <div class="shell footer-grid">
            <div>
                <div class="brand footer-brand"><span class="brand-mark"><span></span></span><span><strong>Mobile Arena</strong><small>Prototype storefront</small></span></div>
                <p>{{ ($cms['shop_information']['description'] ?? '') ?: 'Brand-new and pre-owned gadgets, refurbished units and repair requests in one local shopping experience.' }}</p>
            </div>
            <div>
                <h3>Visit the store</h3>
                <p>{!! nl2br(e(($cms['shop_information']['location_text'] ?? '') ?: "XentroMall Calapan\nRoxas Drive, Lumang Bayan\nCalapan City, Oriental Mindoro 5200")) !!}</p>
            </div>
            <div>
                <h3>Public mall hours</h3>
                <p>{!! nl2br(e(($cms['shop_information']['opening_hours'] ?? '') ?: "Monday–Sunday\n9:00 AM–9:00 PM")) !!}</p>
            </div>
            <div>
                <h3>Store contact</h3>
                @if(!empty($cms['shop_information']['contact_information']))<p>{!! nl2br(e($cms['shop_information']['contact_information'])) !!}</p>
                @else<p><a href="tel:+639995792317">0999 579 2317</a><br><span class="muted">From a public business listing; confirm before launch.</span></p>@endif
            </div>
        </div>
        <div class="shell footer-bottom">Prototype prepared for Mobile Arena · Public location information verified September 2026. · <a href="{{ route('image-credits') }}">Product image credits</a></div>
    </footer>

    <nav class="mobile-nav" aria-label="Mobile navigation">
        <a href="{{ route('home') }}"><svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9H6v-9"/></svg><span>Home</span></a>
        <a href="{{ route('products.index') }}"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 5a4 4 0 0 1 8 0"/></svg><span>Shop</span></a>
        <a href="{{ route('repair.create') }}"><svg viewBox="0 0 24 24"><path d="m14 6 4-4 4 4-4 4M4 20l9-9 4 4-9 9H4z"/></svg><span>Repair</span></a>
        <a href="{{ route('cart.index') }}"><svg viewBox="0 0 24 24"><path d="M3 4h2l2 11h10l2-8H6"/></svg><span>Cart ({{ \App\Http\Controllers\CartController::count() }})</span></a>
        @auth<a href="{{ route('account.index') }}"><span class="mobile-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span>Account</span></a>@else<a href="{{ route('login') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3-7 8-7s8 3 8 7"/></svg><span>Sign in</span></a>@endauth
    </nav>
</body>
</html>
