@extends('layouts.app')
@section('title', 'Refund requests')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">My account</span><h1>Refund requests</h1><p>Track requests reviewed by Mobile Arena staff.</p></div></section>
<div class="shell account-layout">@include('account.partials.nav')
    <div class="account-content">
        @forelse($refunds as $refund)
            <article class="clay-card refund-card"><div class="purchase-head"><div><span class="eyebrow">{{ $refund->order->reference }}</span><h2>Refund request</h2><p>Requested {{ $refund->requested_at->format('M j, Y') }}</p></div><span class="status-pill">{{ $refund->status_label }}</span></div><p>{{ $refund->reason }}</p><div class="purchase-footer"><span>Amount <strong>₱{{ number_format((float) $refund->amount, 2) }}</strong></span><a class="btn btn-secondary" href="{{ route('account.orders.show', $refund->order) }}">View order</a></div></article>
        @empty
            <div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">↺</span><h2>No refund requests</h2><p>Eligible completed orders have a refund request option in their order details.</p><a class="btn btn-primary" href="{{ route('account.purchases') }}">View purchases</a></div>
        @endforelse
        {{ $refunds->links() }}
    </div>
</div>
@endsection
