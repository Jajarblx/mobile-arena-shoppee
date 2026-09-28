<nav class="account-nav clay-card" aria-label="Account sections">
    <a href="{{ route('account.index') }}" @class(['active' => request()->routeIs('account.index')])>Overview</a>
    <a href="{{ route('account.edit') }}" @class(['active' => request()->routeIs('account.edit')])>Edit profile</a>
    <a href="{{ route('account.purchases') }}" @class(['active' => request()->routeIs('account.purchases', 'account.orders.show')])>My purchases</a>
    <a href="{{ route('account.refunds') }}" @class(['active' => request()->routeIs('account.refunds')])>Refund requests</a>
    <a href="{{ route('account.notifications') }}" @class(['active' => request()->routeIs('account.notifications*')])>Notifications @if(auth()->user()->unreadNotifications()->count())<span class="nav-count">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
    <a href="{{ route('account.repairs') }}" @class(['active' => request()->routeIs('account.repairs')])>Repair requests</a>
    <a href="{{ route('account.addresses') }}" @class(['active' => request()->routeIs('account.addresses*')])>Addresses</a>
    <a href="{{ route('account.wishlist') }}" @class(['active' => request()->routeIs('account.wishlist')])>Wishlist</a>
</nav>
