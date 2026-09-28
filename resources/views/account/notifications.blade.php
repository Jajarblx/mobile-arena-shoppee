@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">My account</span><h1>Notifications</h1><p>Order, payment, refund and repair updates in one place.</p></div></section>
<div class="shell account-layout">@include('account.partials.nav')
    <div class="account-content">
        <div class="account-section-head"><h2>Recent updates</h2>@if(auth()->user()->unreadNotifications()->exists())<form method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn btn-secondary" type="submit">Mark all as read</button></form>@endif</div>
        @forelse($notifications as $notification)
            <article @class(['clay-card notification-card', 'notification-unread' => ! $notification->read_at])>
                <div><span class="eyebrow">{{ str_replace(['.', '_'], ' ', $notification->data['kind'] ?? 'Update') }}</span><h2>{{ $notification->data['title'] ?? 'Mobile Arena update' }}</h2><p>{{ $notification->data['body'] ?? '' }}</p><small>{{ $notification->created_at->format('M j, Y · g:i A') }}</small></div>
                <div class="notification-actions"><a class="btn btn-secondary" href="{{ $notification->data['url'] ?? route('account.index') }}">View details</a>@if(! $notification->read_at)<form method="POST" action="{{ route('account.notifications.read', $notification->id) }}">@csrf<button class="text-btn" type="submit">Mark read</button></form>@endif</div>
            </article>
        @empty
            <div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">✦</span><h2>No notifications yet</h2><p>Updates about your orders and repairs will appear here.</p><a class="btn btn-primary" href="{{ route('account.index') }}">Back to account</a></div>
        @endforelse
        {{ $notifications->links() }}
    </div>
</div>
@endsection
