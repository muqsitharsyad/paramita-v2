@extends('layouts.auth')

@section('title', __('ui.auth.status_title'))

@section('content')
<div class="auth-logo">
    <h3>{{ __('ui.auth.register_title') }}</h3>
    <span class="badge bg-warning text-dark text-uppercase px-3 py-2 mt-2">{{ __('ui.auth.status_waiting') }}</span>
</div>

<div class="text-center">
    <p class="text-secondary small mb-4">
        {{ __('ui.auth.status_registration_for') }} <strong>{{ $vendor->legal_name ?? __('ui.common.vendor') }}</strong> {{ __('ui.auth.status_received') }}
    </p>
    <a href="{{ route('login') }}" class="btn btn-outline-primary w-100 py-2 fw-semibold">{{ __('ui.auth.back_to_login') }}</a>
</div>
@endsection
