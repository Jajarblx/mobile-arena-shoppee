@extends('layouts.app')
@section('title', 'Repair requests')
@section('content')
<section class="page-hero compact">
    <div class="shell"><span class="eyebrow">My account</span><h1>Repair requests</h1><p>See the latest details for your device service requests.</p></div>
</section>
<div class="shell account-layout">
    @include('account.partials.nav')
    <div class="account-content">
        @forelse($repairs as $repair)
            <article class="repair-request-card clay-card">
                <div class="purchase-head">
                    <div><span class="eyebrow">{{ $repair->reference }}</span><h2>{{ $repair->brand_model }}</h2><p>Requested {{ $repair->created_at->format('M j, Y') }} · {{ ucfirst($repair->service_type) }}</p></div>
                    <span class="status-pill">{{ $repair->status_label }}</span>
                </div>
                <div class="repair-request-body">
                    <div><strong>Reported issue</strong><p>{{ $repair->issue }}</p></div>
                    @if($repair->preferred_date)
                        <div><strong>Preferred visit</strong><p>{{ $repair->preferred_date->format('M j, Y') }}</p></div>
                    @endif
                    @if($repair->estimated_cost !== null)
                        <div><strong>Estimated cost</strong><p>₱{{ number_format((float) $repair->estimated_cost, 2) }}</p></div>
                    @endif
                    @if($repair->technician_notes)
                        <div><strong>Technician notes</strong><p>{{ $repair->technician_notes }}</p></div>
                    @endif
                </div>
                @if($repair->statusEvents->isNotEmpty())<ol class="repair-timeline">@foreach($repair->statusEvents as $event)<li><strong>{{ \App\Models\RepairBooking::STATUS_LABELS[$event->status] ?? ucfirst($event->status) }}</strong><span>{{ $event->created_at->format('M j, Y · g:i A') }}</span>@if($event->note)<p>{{ $event->note }}</p>@endif</li>@endforeach</ol>@endif
            </article>
        @empty
            <div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">✦</span><h2>No repair requests yet</h2><p>Tell us about your device and your service request will appear here.</p><a class="btn btn-primary" href="{{ route('repair.create') }}">Book an assessment</a></div>
        @endforelse
        {{ $repairs->links() }}
    </div>
</div>
@endsection
