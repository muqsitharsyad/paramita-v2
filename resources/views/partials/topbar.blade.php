<nav class="paramita-topbar" aria-label="{{ __('ui.action.account') }}">
    <button type="button" class="btn btn-outline-secondary" id="menu-toggle" aria-controls="sidebar-wrapper" aria-expanded="true">{{ __('ui.action.menu') }}</button>
    <div class="topbar-account ms-auto">
        {{-- Language switcher: preserves the current path and query string. --}}
        <div class="lang-switch" role="group" aria-label="{{ __('ui.lang.switch_to') }}">
            @foreach(config('paramita.locales', []) as $code => $label)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                   class="lang-switch-option {{ app()->getLocale() === $code ? 'is-active' : '' }}"
                   hreflang="{{ $code }}"
                   title="{{ $label }}"
                   @if(app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</a>
            @endforeach
        </div>

        @auth
            <span class="topbar-name">{{ auth()->user()->name }}</span>
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('ui.action.logout') }}</button></form>
        @else
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('login') }}">{{ __('ui.action.login') }}</a>
        @endauth
    </div>
</nav>
