@extends('layouts.auth')

@section('title', __('ui.auth.register_title'))

@section('content')
<div class="auth-logo">
    <h3>{{ __('ui.auth.register_title') }}</h3>
    <p>{{ __('ui.auth.register_subtitle') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2 small mb-4">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('register') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.legal_name') }}</label>
        <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.contact_name') }}</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.contact_email') }}</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.password_hint') }}</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.password_confirm') }}</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <div class="mb-3 form-check">
        <input type="checkbox" name="consent" value="1" class="form-check-input" id="consentCheck" required>
        <label class="form-check-label small text-muted" for="consentCheck">
            {{ __('ui.auth.agree') }}
        </label>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mt-2">{{ __('ui.auth.register') }}</button>
</form>

<div class="mt-4 text-center small text-muted border-top pt-3">
    {{ __('ui.auth.have_account') }} <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">{{ __('ui.action.login') }}</a>
</div>
@endsection
