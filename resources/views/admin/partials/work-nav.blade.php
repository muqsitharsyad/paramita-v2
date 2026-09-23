<nav class="admin-work-nav" aria-label="{{ __('ui.admin.navigasi') }}">
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->is('admin') ? 'is-active' : '' }}">{{ __('ui.admin.ringkasan') }}</a>
    <a href="{{ route('admin.vendors.index') }}" class="{{ request()->is('admin/vendors*') ? 'is-active' : '' }}">
        {{ __('ui.common.vendor') }}
        @isset($stats)<span>{{ $stats['pending_vendors'] }}</span>@endisset
    </a>
    <a href="{{ route('admin.endpointReviews.index') }}" class="{{ request()->is('admin/endpoint-reviews*') ? 'is-active' : '' }}">
        {{ __('ui.admin.review_endpoint') }}
        @isset($stats)<span>{{ $stats['pending_endpoints'] }}</span>@endisset
    </a>
    <a href="{{ route('admin.activeEndpoints.index') }}" class="{{ request()->is('admin/active-endpoints*') ? 'is-active' : '' }}">
        {{ __('ui.admin.active_endpoint') }}
        @isset($stats)<span>{{ $stats['active_bindings'] }}</span>@endisset
    </a>
    <a href="{{ route('admin.templates.index') }}" class="{{ request()->is('admin/templates*') ? 'is-active' : '' }}">{{ __('ui.nav.json_templates') }}</a>
    <a href="{{ route('admin.reference.index', 'ut') }}" class="{{ request()->is('admin/reference*') ? 'is-active' : '' }}">{{ __('ui.nav.master_data') }}</a>
    <a href="{{ route('admin.users.index') }}" class="{{ request()->is('admin/users*') ? 'is-active' : '' }}">{{ __('ui.users.title') }}</a>
</nav>
