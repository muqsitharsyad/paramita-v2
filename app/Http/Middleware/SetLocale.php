<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the UI language for every web request.
 *
 * Order: explicit ?lang= (also persisted to the session) -> session -> authenticated user
 * preference -> config default. Supported codes are limited to config('paramita.locales'),
 * so a crafted ?lang= can never walk the filesystem.
 *
 * A ?lang= hit only changes the SESSION preference. The persisted per-user preference is
 * changed deliberately from the language menu, never as a side effect of a URL parameter —
 * otherwise a shared/QA link would silently rewrite someone's setting.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('paramita.locales', ['id' => 'Bahasa Indonesia']));
        $locale = null;

        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, $supported, true)) {
            $locale = $requested;
            $request->session()->put('locale', $requested);
        }

        if ($locale === null) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
                $locale = $sessionLocale;
            }
        }

        if ($locale === null) {
            $userLocale = $request->user()?->locale;
            $locale = is_string($userLocale) && in_array($userLocale, $supported, true)
                ? $userLocale
                : (string) config('paramita.default_locale', 'id');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
