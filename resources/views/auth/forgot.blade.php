@extends('layouts.auth')

@section('title', __('ui.auth.forgot_title'))

@section('content')
<div class="auth-logo">
    <h3>{{ __('ui.auth.forgot_title') }}</h3>
    <p>{{ __('ui.auth.forgot_subtitle') }}</p>
</div>

@if (session('status'))
    <div class="alert alert-success py-2 small mb-4">
        {{ session('status') }}
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.email') }}</label>
        <input type="email" name="email" class="form-control form-control-lg fs-6" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mt-2">{{ __('ui.auth.send_link') }}</button>
</form>

<div class="mt-4 text-center small text-muted border-top pt-3">
    {{ __('ui.auth.remember') }} <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">{{ __('ui.action.login') }}</a>
</div>
@endsection
