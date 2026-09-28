@extends('layouts.app')
@section('title', 'Addresses')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="eyebrow">My account</span><h1>Addresses</h1><p>Keep delivery details ready for your next order.</p></div></section>
<div class="shell account-layout">@include('account.partials.nav')<div class="account-content"><div class="account-section-head"><h2>Saved addresses</h2><a class="btn btn-primary" href="{{ route('account.addresses.create') }}">Add address</a></div>
@forelse($addresses as $address)
    <article class="address-card clay-card"><div class="address-main"><div class="address-title"><h3>{{ $address->recipient_name }}</h3>@if($address->is_default)<span class="status-pill">Default</span>@endif</div><p class="address-phone">{{ $address->phone }}</p><p>{{ $address->formatted }}</p></div><div class="address-actions"><a class="btn btn-secondary" href="{{ route('account.addresses.edit', $address) }}">Edit</a>@unless($address->is_default)<form method="POST" action="{{ route('account.addresses.default', $address) }}">@csrf @method('PATCH')<button class="text-btn" type="submit">Set as default</button></form>@endunless<form method="POST" action="{{ route('account.addresses.destroy', $address) }}">@csrf @method('DELETE')<button class="text-btn danger" type="submit">Delete</button></form></div></article>
@empty
    <div class="empty-state clay-card"><span class="empty-icon" aria-hidden="true">⌂</span><h2>No saved addresses yet</h2><p>Add your first delivery address to make checkout easier.</p><a class="btn btn-primary" href="{{ route('account.addresses.create') }}">Add an address</a></div>
@endforelse</div></div>
@endsection
