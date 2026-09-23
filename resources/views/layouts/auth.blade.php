<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.brand.name') }} | @yield('title', __('ui.action.login'))</title>
    <link rel="stylesheet" href="{{ asset('style/main.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-intro" aria-label="{{ __('ui.brand.about') }}"><img src="{{ asset('images/logo.svg') }}" alt="{{ __('ui.brand.name') }}"><p class="auth-kicker">{{ __('ui.brand.university') }}</p><h1>{{ __('ui.brand.subtitle') }}</h1><p>{{ __('ui.brand.login_intro') }}</p>
            <div class="lang-switch auth-lang-switch" role="group" aria-label="{{ __('ui.lang.switch_to') }}">
                @foreach(config('paramita.locales', []) as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="lang-switch-option {{ app()->getLocale() === $code ? 'is-active' : '' }}" hreflang="{{ $code }}">{{ strtoupper($code) }}</a>
                @endforeach
            </div>
        </section>
        <section class="auth-panel"><div class="auth-card">@yield('content')</div></section>
    </main>
    @yield('scripts')
</body>
</html>
