@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">
            <h4 class="card-title fw-bold mb-3">{{ __('ui.auth.verify_title') }}</h4>
            <p class="text-muted small mb-4">{{ __('ui.auth.verify_notice') }}</p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.auth.resend_verification') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
