@php($currentUser = auth()->user())
@php($isVendor = $currentUser?->role === 'vendor')
@php($isRegionalHead = $currentUser?->role === 'kepala_ut_daerah')

<aside id="sidebar-wrapper" class="paramita-sidebar" aria-label="{{ __('ui.nav.main') }}">
    <a class="sidebar-brand" href="{{ $isVendor ? route('vendor.dashboard') : ($isRegionalHead ? url('/do-per-ut-daerah') : url('/dashboard')) }}" aria-label="{{ __('ui.nav.home') }}">
        <img src="{{ asset('images/logo.svg') }}" alt="{{ __('ui.brand.name') }}">
        <span>{{ __('ui.brand.name') }}</span>
    </a>
    <div class="sidebar-user">
        <img src="{{ asset('images/avatar.svg') }}" alt="" aria-hidden="true">
        <div><strong>{{ $currentUser->name ?? __('ui.nav.user_fallback') }}</strong><small>{{ strtoupper(str_replace('_', ' ', $currentUser->role ?? 'Internal')) }}</small></div>
    </div>

    <nav class="sidebar-nav">
        @if(!$isVendor)
            <p>{{ __('ui.nav.monitoring') }}</p>
            @if(!$isRegionalHead)
                <a href="{{ url('/dashboard') }}" class="{{ request()->is('dashboard') ? 'is-active' : '' }}">{{ __('ui.nav.stock_monitoring') }}</a>
                <a href="{{ url('/monitoring-delivery') }}" class="{{ request()->is('monitoring-delivery') ? 'is-active' : '' }}">{{ __('ui.nav.delivery_monitoring') }}</a>
                <a href="{{ url('/do-per-prodi') }}" class="{{ request()->is('do-per-prodi') ? 'is-active' : '' }}">{{ __('ui.nav.do_per_prodi') }}</a>
            @endif
            <a href="{{ url('/do-per-ut-daerah') }}" class="{{ request()->is('do-per-ut-daerah') ? 'is-active' : '' }}">{{ __('ui.nav.do_per_ut') }}</a>
            <a href="{{ url('/analisis-sla') }}" class="{{ request()->is('analisis-sla') ? 'is-active' : '' }}">{{ __('ui.nav.sla_analysis') }}</a>
            <a href="{{ url('/monitoring-retry') }}" class="{{ request()->is('monitoring-retry') ? 'is-active' : '' }}">{{ __('ui.nav.retry_monitoring') }}</a>
            @if(!$isRegionalHead)
                <a href="{{ url('/distribution-map') }}" class="{{ request()->is('distribution-map') ? 'is-active' : '' }}">{{ __('ui.nav.distribution_map') }}</a>
            @endif
        @endif
        @if($currentUser?->role === 'admin')
            <p>{{ __('ui.nav.administration') }}</p>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->is('admin*') ? 'is-active' : '' }}">{{ __('ui.nav.admin_center') }}</a>
        @endif
        @if($isVendor)
            <p>{{ __('ui.nav.vendor_integration') }}</p>
            <a href="{{ route('vendor.dashboard') }}" class="{{ request()->is('vendor-portal*') ? 'is-active' : '' }}">{{ __('ui.vendor.portal') }}</a>
        @endif
    </nav>
</aside>
