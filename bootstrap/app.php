<?php

use App\Http\Middleware\EnsureApprovedVendorPortalAccess;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\SetLocale;
use App\Services\Monitoring\MonitoringException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The production app is exposed through a trusted reverse proxy.
        // Trust its forwarded prefix so generated forms, redirects, and links retain the
        // deployment subpath (for example, /paramita-final).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->alias([
            'role' => EnsureUserRole::class,
            'approved-vendor' => EnsureApprovedVendorPortalAccess::class,
        ]);

        // Resolve the UI language for every web request (session + user preference + ?lang=).
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (MonitoringException $exception, Request $request) {
            $title = $exception->status === 403 ? 'Forbidden' : 'Invalid Parameters';

            return response()->json([
                'type' => 'https://paramita-final.test/errors/'.strtolower(str_replace('_', '-', $exception->errorCode)),
                'title' => $title,
                'status' => $exception->status,
                'detail' => $exception->errorCode,
                'instance' => $request->path(),
                'request_id' => 'REQ-'.strtoupper(uniqid()),
            ], $exception->status, ['Content-Type' => 'application/problem+json']);
        });
    })->create();
