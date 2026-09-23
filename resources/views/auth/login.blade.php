@extends('layouts.auth')

@section('title', __('ui.action.login'))

@section('content')
<div class="auth-logo">
    <h3>{{ __('ui.brand.name') }} UT</h3>
    <p>{{ __('ui.brand.tagline') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2 small mb-4">
        {{ $errors->first() }}
    </div>
@endif

@if (session('status'))
    <div class="alert alert-success py-2 small mb-4">
        {{ session('status') }}
    </div>
@endif

<a href="{{ route('auth.microsoft.redirect') }}" class="btn auth-sso-button">
    <svg viewBox="0 0 23 23" aria-hidden="true">
        <path fill="#f35325" d="M1 1h10v10H1z" />
        <path fill="#81bc06" d="M12 1h10v10H12z" />
        <path fill="#05a6f0" d="M1 12h10v10H1z" />
        <path fill="#ffba08" d="M12 12h10v10H12z" />
    </svg>
    <span>{{ __('ui.auth.sso_microsoft') }}</span>
</a>
<div class="auth-divider" role="separator"><span>{{ __('ui.auth.sso_separator') }}</span></div>

<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.email') }}</label>
        <input id="email" type="email" name="email" class="form-control fs-6" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </div>
    <div class="mb-3">
        <div class="d-flex justify-content-between">
            <label class="form-label small fw-semibold text-secondary">{{ __('ui.auth.password') }}</label>
            <a href="{{ route('password.request') }}" class="small text-decoration-none">{{ __('ui.auth.forgot') }}</a>
        </div>
        <div class="auth-password">
            <input id="password" type="password" name="password" class="form-control fs-6" required>
            <button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="{{ __('ui.auth.password') }}" aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary auth-submit mt-2">{{ __('ui.auth.login_title') }}</button>
</form>

<div class="mt-4 text-center small text-muted border-top pt-3">
    {{ __('ui.auth.new_vendor') }} <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">{{ __('ui.auth.register') }}</a>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"></path><path d="M1 1l22 22"></path></svg>';
    document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.getAttribute('data-password-toggle'));
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? EYE_OFF : EYE;
            btn.setAttribute('aria-pressed', String(show));
        });
    });
})();
</script>
@endsection
