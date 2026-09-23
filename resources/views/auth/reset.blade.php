@extends('layouts.auth')

@section('title', __('ui.auth.new_password'))

@section('content')
<div class="auth-logo">
    <h3>{{ __('ui.auth.reset_title') }}</h3>
    <p>{{ __('ui.auth.reset_subtitle') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2 small mb-4">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.email') }}</label>
        <input type="email" name="email" class="form-control form-control-lg fs-6" value="{{ $email }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.new_password') }}</label>
        <input type="password" name="password" class="form-control form-control-lg fs-6" required>
    </div>
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.password_confirm') }}</label>
        <input type="password" name="password_confirmation" class="form-control form-control-lg fs-6" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mt-2">{{ __('ui.auth.save_changes') }}</button>
</form>
@endsection
