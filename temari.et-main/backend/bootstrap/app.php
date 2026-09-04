<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SetActiveContext;
use App\Services\Analytics\Analytics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Websocket channel auth (Reverb): the SPA authorizes with its Sanctum
    // bearer token against POST /api/broadcasting/auth.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Coolify's Traefik reverse proxy — trust it so https/host detection works.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active.context' => SetActiveContext::class,
            'active.account' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every reportable exception (validation/404/auth are already
        // excluded by the framework) also lands in PostHog error tracking.
        $exceptions->report(function (Throwable $e): void {
            Analytics::captureException($e);
        });
    })->create();
