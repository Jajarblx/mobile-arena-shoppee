@extends('layouts.app')
@section('title','Service request received')
@section('content')
<section class="success-wrap shell"><div class="success-card clay-panel"><div class="success-icon">✓</div><span class="eyebrow">Service request saved</span><h1>{{ $booking->reference }}</h1><p>Your {{ $booking->brand_model }} request is recorded. This prototype does not promise a repair price or completion time; the store needs to inspect the device first.</p><div class="success-meta"><div><span>Service</span><strong>{{ ucfirst($booking->service_type) }}</strong></div><div><span>Status</span><strong>{{ ucfirst($booking->status) }}</strong></div>@if($booking->preferred_date)<div><span>Preferred date</span><strong>{{ $booking->preferred_date->format('M j, Y') }}</strong></div>@endif</div><div class="hero-actions"><a class="btn btn-primary" href="{{ route('home') }}">Back home</a><a class="btn btn-secondary" href="{{ route('products.index') }}">Browse shop</a></div></div></section>
@endsection
