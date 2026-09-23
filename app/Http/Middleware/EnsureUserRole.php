<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'active' && in_array($user->role, $roles, true), 403);

        if ($user->role === 'vendor') {
            abort_unless($user->vendor_id !== null, 403);
        }

        return $next($request);
    }
}
