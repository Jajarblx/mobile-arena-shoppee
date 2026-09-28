@extends('layouts.app')
@section('title', 'Refund reviews')
@section('content')
<section class="page-hero compact"><div class="shell"><a class="back-link" href="{{ route('admin.dashboard') }}">← Operations dashboard</a><span class="eyebrow">Staff reviews</span><h1>Refund requests</h1><p>Review eligibility, record your decision, and mark external refunds complete only after staff handling.</p></div></section>
<div class="shell admin-history-layout">
    <nav class="purchase-tabs" aria-label="Refund status filters"><a href="{{ route('admin.refunds.index') }}" @class(['active' => $status === null])>All</a>@foreach(\App\Models\Refund::STATUS_LABELS as $key => $label)<a href="{{ route('admin.refunds.index', ['status' => $key]) }}" @class(['active' => $status === $key])>{{ $label }}</a>@endforeach</nav>
    @forelse($refunds as $refund)
        <article class="clay-card refund-review-card">
            <div class="purchase-head"><div><span class="eyebrow">{{ $refund->order->reference }}</span><h2>{{ $refund->user->name }}</h2><p>Requested {{ $refund->requested_at->format('M j, Y · g:i A') }} · <a href="{{ route('admin.orders.show', $refund->order) }}">View order</a></p></div><span class="status-pill">{{ $refund->status_label }}</span></div>
            <div class="refund-review-grid"><div><h3>Customer reason</h3><p>{{ $refund->reason }}</p><p><strong>Requested amount:</strong> ₱{{ number_format((float) $refund->amount, 2) }}<br><strong>Order total:</strong> ₱{{ number_format($refund->order->total, 2) }}</p>@if($refund->staff_notes)<h3>Staff update</h3><p>{{ $refund->staff_notes }}</p>@endif</div>
            <div>
                @if(in_array($refund->status, ['requested', 'under_review']))
                    <form class="staff-action-form" method="POST" action="{{ route('admin.refunds.review', $refund) }}">@csrf @method('PATCH')
                        <label for="refund-status-{{ $refund->id }}">Decision</label><select id="refund-status-{{ $refund->id }}" name="status"><option value="under_review">Under review</option><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                        <label for="refund-amount-{{ $refund->id }}">Approved amount, if approving</label><input id="refund-amount-{{ $refund->id }}" type="number" name="amount" min="0.01" step="0.01" max="{{ $refund->order->total }}" value="{{ $refund->amount }}">
                        <label for="refund-note-{{ $refund->id }}">Staff update (optional)</label><textarea id="refund-note-{{ $refund->id }}" name="staff_notes" rows="3">{{ $refund->staff_notes }}</textarea><button class="btn btn-primary" type="submit">Save review</button>
                    </form>
                @elseif($refund->status === 'approved')
                    <form class="staff-action-form" method="POST" action="{{ route('admin.refunds.complete', $refund) }}" onsubmit="return confirm('Mark this external refund complete? Inventory may be restored based on the return condition.')">@csrf
                        <label for="return-{{ $refund->id }}">Returned item condition</label><select id="return-{{ $refund->id }}" name="return_disposition" required><option value="not_returned">Not returned</option><option value="sellable">Returned and sellable — restore stock</option><option value="damaged">Returned damaged — do not restore stock</option></select>
                        <label for="complete-note-{{ $refund->id }}">Staff update (optional)</label><textarea id="complete-note-{{ $refund->id }}" name="staff_notes" rows="3">{{ $refund->staff_notes }}</textarea><button class="btn btn-primary" type="submit">Mark refund complete</button><p class="microcopy">Record the actual money transfer outside this prototype.</p>
                    </form>
                @else<p>No further normal action is available.</p>@endif
            </div></div>
        </article>
    @empty<div class="empty-state clay-card"><h2>No refund requests here</h2><p>Try another status filter.</p></div>@endforelse
    {{ $refunds->links() }}
</div>
@endsection
