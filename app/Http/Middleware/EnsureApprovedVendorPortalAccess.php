<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Policies\PortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApprovedVendorPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(PortalAccess::vendor($request->user()), 403);

        return $next($request);
    }
}
