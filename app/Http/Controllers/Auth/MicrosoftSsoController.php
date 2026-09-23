<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class MicrosoftSsoController extends Controller
{
    private const INTERNAL_ROLES = ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor'];

    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure(__('ui.auth.sso_not_configured'));
        }

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->failure(__('ui.auth.sso_not_configured'));
        }

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            Log::warning('Microsoft SSO callback failed.', [
                'exception' => $exception::class,
                'ip' => $request->ip(),
            ]);

            return $this->failure();
        }

        $microsoftId = trim((string) $microsoftUser->getId());
        $raw = $microsoftUser->getRaw();
        $email = strtolower(trim((string) ($raw['mail'] ?? $microsoftUser->getEmail() ?? $raw['userPrincipalName'] ?? '')));

        if ($microsoftId === '' || $email === '') {
            return $this->failure();
        }

        $user = DB::transaction(function () use ($email, $microsoftId): ?User {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $this->canUseSso($user)) {
                return null;
            }

            if ($user->microsoft_id !== null && ! hash_equals($user->microsoft_id, $microsoftId)) {
                return null;
            }

            $identityBelongsToAnotherUser = User::query()
                ->where('microsoft_id', $microsoftId)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($identityBelongsToAnotherUser) {
                return null;
            }

            $user->forceFill([
                'microsoft_id' => $user->microsoft_id ?? $microsoftId,
                'last_sso_login_at' => now(),
            ])->save();

            return $user;
        });

        if (! $user) {
            return $this->failure(__('ui.auth.sso_account_unavailable'));
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect($this->destinationFor($user));
    }

    private function canUseSso(?User $user): bool
    {
        return $user !== null
            && $user->status === 'active'
            && $user->email_verified_at !== null
            && $user->vendor_id === null
            && in_array($user->role, self::INTERNAL_ROLES, true);
    }

    private function destinationFor(User $user): string
    {
        return match ($user->role) {
            'admin' => '/admin',
            'kepala_ut_daerah' => '/do-per-ut-daerah',
            default => '/dashboard',
        };
    }

    private function isConfigured(): bool
    {
        return (bool) config('services.microsoft.enabled')
            && filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.redirect'))
            && filled(config('services.microsoft.tenant'));
    }

    private function failure(?string $message = null): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'sso' => $message ?? __('ui.auth.sso_failed'),
        ]);
    }
}
