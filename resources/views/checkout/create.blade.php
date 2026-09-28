@extends('layouts.app')
@section('title', 'Checkout')
@section('content')
@php
    $deliverySelected = old('fulfillment', 'pickup') === 'delivery_request';
    $addressChoice = old('address_choice', $addresses->isNotEmpty() ? 'saved' : 'new');
    $newAddressActive = $deliverySelected && $addressChoice === 'new';
    $savedAddressActive = $deliverySelected && $addressChoice === 'saved';
    $pricing = app(\App\Services\DeliveryPricing::class);
@endphp
<section class="page-hero compact"><div class="shell"><span class="eyebrow">Secure checkout</span><h1>Review your order</h1><p>Confirm your delivery or pickup details before placing your order.</p></div></section>
<form class="shell checkout-grid" method="POST" action="{{ route('checkout.store') }}" data-submit-once data-checkout-pricing data-subtotal="{{ $subtotal }}" data-fees='@json(config('mobile-arena.delivery.fees'))' data-origin='@json(config('mobile-arena.delivery.origin'))'>
    @csrf
    <input type="hidden" name="checkout_token" value="{{ $token }}">
    <div class="form-card clay-card">
        <h2>Contact information</h2>
        <div class="form-grid">
            <label for="customer_name">Full name<input id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" autocomplete="name" required></label>
            <label for="phone">Contact number<input id="phone" name="phone" type="tel" value="{{ old('phone', auth()->user()->mobile) }}" autocomplete="tel" required></label>
            <label class="span-2" for="email">Email address<input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email"></label>
        </div>
        <h2>Fulfillment</h2>
        <div class="choice-grid">
            <label class="choice"><input type="radio" name="fulfillment" value="pickup" @checked(! $deliverySelected)><span><strong>Store pickup</strong><small>XentroMall Calapan · Free</small></span></label>
            <label class="choice"><input type="radio" name="fulfillment" value="delivery_request" @checked($deliverySelected)><span><strong>Delivery</strong><small>Fee is based on your Philippine address.</small></span></label>
        </div>
        <div class="delivery-options" data-delivery-options @if(! $deliverySelected) hidden @endif>
            <h3>Delivery address</h3>
            <div class="choice-grid address-choice-grid">
                @if($addresses->isNotEmpty())<label class="choice"><input type="radio" name="address_choice" value="saved" @checked($addressChoice === 'saved')><span><strong>Saved address</strong><small>Choose from your address book.</small></span></label>@endif
                <label class="choice"><input type="radio" name="address_choice" value="new" @checked($addressChoice === 'new')><span><strong>New address</strong><small>Save it to your account for next time.</small></span></label>
            </div>
            @if($addresses->isNotEmpty())
                <div data-saved-address @if(! $savedAddressActive) hidden @endif>
                    <label for="address_id">Select saved address</label>
                    <select id="address_id" name="address_id" @disabled(! $savedAddressActive)>
                        @foreach($addresses as $address)
                            <option value="{{ $address->id }}" data-fee="{{ $pricing->forAddress($address)['fee'] }}" @selected(old('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id) == $address->id)>{{ $address->recipient_name }} · {{ $address->street_address }}, {{ $address->barangay }}, {{ $address->city }} @if($address->is_default)(Default)@endif</option>
                        @endforeach
                    </select>
                    <a class="address-manage-link" href="{{ route('account.addresses') }}">Manage addresses</a>
                </div>
            @endif
            <fieldset data-new-address @if(! $newAddressActive) hidden disabled @endif>
                <legend>New delivery address</legend>
                @include('account.partials.address-fields', ['prefix' => 'new_address', 'address' => null])
                <label class="check-label"><input type="checkbox" name="save_as_default" value="1" @checked(old('save_as_default'))><span>Make this my default address</span></label>
            </fieldset>
        </div>
        <h2>Payment method</h2>
        <div class="choice-grid">
            <label class="choice"><input type="radio" name="payment_method" value="pay_at_store" @checked(old('payment_method', 'pay_at_store') === 'pay_at_store')><span><strong>Pay at store</strong><small>Pay when collecting your order.</small></span></label>
            <label class="choice"><input type="radio" name="payment_method" value="cash_on_pickup" @checked(old('payment_method') === 'cash_on_pickup')><span><strong>Cash on pickup</strong><small>For store collection only.</small></span></label>
            <label class="choice"><input type="radio" name="payment_method" value="cash_on_delivery" @checked(old('payment_method') === 'cash_on_delivery')><span><strong>Cash on delivery</strong><small>Pay when your order arrives.</small></span></label>
            <label class="choice"><input type="radio" name="payment_method" value="gcash_manual" @checked(old('payment_method') === 'gcash_manual')><span><strong>GCash</strong><small>Manual reference verification; no payment is taken here.</small></span></label>
        </div>
        <p class="microcopy">For GCash, place the order first, use payment details confirmed by Mobile Arena, then submit your transaction reference. The reservation lasts {{ config('mobile-arena.inventory.reservation_minutes') }} minutes while unpaid.</p>
        <label for="notes">Order notes (optional)</label><textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
    </div>
    <aside class="order-summary clay-card">
        <span class="eyebrow">Your items</span>
        <div class="checkout-items">
            @foreach($items as $item)
                <div class="checkout-item"><img src="{{ $item['product']->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ asset('images/products/phone-blue.svg') }}'"><div><strong>{{ $item['product']->name }}</strong><small>Qty {{ $item['quantity'] }} · ₱{{ number_format((float) $item['product']->price, 2) }} each</small></div><b>₱{{ number_format($item['line_total'], 2) }}</b></div>
            @endforeach
        </div>
        <div class="mini-line"><span>Items subtotal</span><strong>₱{{ number_format($subtotal, 2) }}</strong></div>
        <div class="mini-line"><span>Delivery fee</span><strong data-delivery-fee>Free pickup</strong></div>
        <div class="summary-total"><span>Order total</span><strong data-order-total>₱{{ number_format($subtotal, 2) }}</strong></div>
        <button class="btn btn-primary btn-full" type="submit" data-busy-label="Placing order…">Place order</button>
        <p class="microcopy">Final stock, item prices and delivery fee are checked again when you place the order. No online payment is processed here.</p>
    </aside>
</form>
@endsection
