@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<section class="auth-section auth-centered shell"><div class="auth-card clay-card"><div class="auth-card-head"><span class="auth-icon" aria-hidden="true">MA</span><h1>Reset your password</h1><p>Enter your account email and we’ll send a secure reset link.</p></div><form action="{{ route('password.email') }}" method="POST" class="auth-form" data-submit-once>@csrf<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror<button class="btn btn-primary btn-full" type="submit" data-busy-label="Sending link…">Send reset link</button></form><p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p></div></section>
@endsection
