@extends('layouts.app')
@section('title', 'Create account')
@section('content')
<section class="auth-section shell">
    <div class="auth-intro"><span class="eyebrow">Join Mobile Arena</span><h1>One account for every device need</h1><p>Save products you love and keep shopping and service requests together.</p><div class="auth-benefits"><span>Easy checkout</span><span>Purchase history</span><span>Repair tracking</span></div></div>
    <div class="auth-card clay-card"><div class="auth-card-head"><span class="auth-icon" aria-hidden="true">MA</span><h2>Create your account</h2><p>It only takes a moment to get started.</p></div>
        <form action="{{ route('register.store') }}" method="POST" class="auth-form" data-submit-once>@csrf
            <label for="name">Full name</label><input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">@error('name')<small class="field-error">{{ $message }}</small>@enderror
            <label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror
            <label for="mobile">Mobile number</label><input id="mobile" name="mobile" type="tel" inputmode="tel" value="{{ old('mobile') }}" autocomplete="tel" placeholder="09XXXXXXXXX" required aria-invalid="{{ $errors->has('mobile') ? 'true' : 'false' }}">@error('mobile')<small class="field-error">{{ $message }}</small>@enderror
            <label for="password">Password</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"><button type="button" data-password-toggle aria-label="Show password">Show</button></div><small class="field-hint">Use at least 8 characters with uppercase, lowercase, a number and a symbol.</small>@error('password')<small class="field-error">{{ $message }}</small>@enderror
            <label for="password_confirmation">Confirm password</label><div class="password-field"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button type="button" data-password-toggle aria-label="Show password confirmation">Show</button></div>
            <button class="btn btn-primary btn-full" type="submit" data-busy-label="Creating account…">Create account</button>
        </form><p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</section>
@endsection
