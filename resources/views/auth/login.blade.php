@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<section class="auth-section shell">
    <div class="auth-intro"><span class="eyebrow">Welcome back</span><h1>Your Mobile Arena account</h1><p>Sign in to continue checkout, track your purchases and manage repair requests.</p><div class="auth-benefits"><span>Shop with confidence</span><span>Keep your favorites</span><span>Follow your requests</span></div></div>
    <div class="auth-card clay-card"><div class="auth-card-head"><span class="auth-icon" aria-hidden="true">MA</span><h2>Sign in</h2><p>Use your email address or mobile number.</p></div>
        <form action="{{ route('login.store') }}" method="POST" class="auth-form" data-submit-once>@csrf
            <label for="login">Email or mobile number</label><input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required aria-invalid="{{ $errors->has('login') ? 'true' : 'false' }}" @if($errors->has('login')) aria-describedby="login-error" @endif>@error('login')<small id="login-error" class="field-error">{{ $message }}</small>@enderror
            <div class="field-heading"><label for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div><div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->has('password')) aria-describedby="password-error" @endif><button type="button" data-password-toggle aria-label="Show password">Show</button></div>@error('password')<small id="password-error" class="field-error">{{ $message }}</small>@enderror
            <label class="check-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Remember me on this device</span></label>
            <button class="btn btn-primary btn-full" type="submit" data-busy-label="Signing in…">Sign in</button>
        </form><p class="auth-switch">New to Mobile Arena? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
</section>
@endsection
