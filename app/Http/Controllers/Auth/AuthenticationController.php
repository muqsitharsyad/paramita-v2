<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class AuthenticationController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        }
        RateLimiter::hit($key, 60);
        $user = User::where('email', $data['email'])->first();
        $allowed = $user && $user->status === 'active' && $user->email_verified_at;
        if ($allowed && $user->role === 'vendor') {
            $allowed = DB::table('vendors')->where('id', $user->vendor_id)->where('status', 'approved')->exists();
        } elseif ($allowed) {
            $allowed = $user->vendor_id === null && in_array($user->role, ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor'], true);
        }
        if (! $allowed || ! Auth::attempt($data, false)) {
            throw ValidationException::withMessages(['email' => 'Email, password, atau status akses tidak valid.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        $destination = match ($user->role) {
            'vendor' => '/vendor-portal',
            'admin' => '/admin',
            'kepala_ut_daerah' => '/do-per-ut-daerah',
            default => '/dashboard',
        };

        return redirect($destination);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'legal_name' => 'required|string|max:255', 'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', 'max:1024', PasswordRule::min(12)], 'consent' => 'accepted',
        ]);
        $user = DB::transaction(function () use ($data) {
            $vendorId = DB::table('vendors')->insertGetId([
                'code' => 'V-'.Str::ulid(), 'legal_name' => $data['legal_name'], 'contact_name' => $data['name'],
                'contact_email' => $data['email'], 'status' => 'pending_verification', 'scope_revision' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
                'role' => 'vendor', 'status' => 'inactive', 'vendor_id' => $vendorId, 'ut_id' => null, 'permission_revision' => 1])->save();

            return $user;
        });
        // Explicit notification: the existing User does not implement MustVerifyEmail.
        $user->sendEmailVerificationNotification();

        return redirect(URL::temporarySignedRoute('registration.status', now()->addHours(24), ['user' => $user->id]));
    }

    public function status(User $user)
    {
        abort_unless($user->role === 'vendor', 404);
        $vendor = DB::table('vendors')->where('id', $user->vendor_id)->firstOrFail();

        return view('auth.status', compact('vendor', 'user'));
    }

    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        DB::transaction(function () use ($user) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                event(new Verified($user));
            }
            if ($user->role === 'vendor') {
                DB::table('vendors')->where('id', $user->vendor_id)->where('status', 'pending_verification')->update(['status' => 'pending_approval', 'updated_at' => now()]);
            }
        });

        return $user->role === 'vendor'
            ? redirect(URL::temporarySignedRoute('registration.status', now()->addHours(24), ['user' => $user->id]))
            : redirect()->route('login')->with('status', 'Email terverifikasi. Silakan masuk.');
    }

    public function resend(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        $user = User::where('email', $data['email'])->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Jika akun memerlukan verifikasi, tautan telah dikirim.');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        Password::sendResetLink($data);

        return back()->with('status', 'Jika email terdaftar, tautan reset telah dikirim.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', 'max:1024', PasswordRule::min(12)]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'Tautan reset tidak valid atau kedaluwarsa.']);
        }

        return redirect()->route('login')->with('status', 'Password diperbarui. Silakan masuk.');
    }
}
