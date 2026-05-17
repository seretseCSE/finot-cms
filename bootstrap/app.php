<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocaleMiddleware::class,
            \App\Http\Middleware\TrackUserVisits::class,
            \App\Http\Middleware\ErrorLoggingMiddleware::class,
            \App\Http\Middleware\TrackUserSessions::class,
            \App\Http\Middleware\SessionTimeoutMiddleware::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
            'session.activity' => \App\Http\Middleware\SessionActivityMiddleware::class,
            'set.locale' => \App\Http\Middleware\SetLocaleMiddleware::class,
            'session.timeout' => \App\Http\Middleware\SessionTimeoutMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\InvalidArgumentException $e, \Illuminate\Http\Request $request) {
            if (str_contains($e->getMessage(), 'Malformed UTF-8 characters')) {
                if ($request->expectsJson() || $request->is('livewire*')) {
                    return response()->json([
                        'error' => 'A data encoding error occurred. Please ensure all input contains valid UTF-8 characters.',
                    ], 500);
                }

                return back()->with('error', 'A data encoding error occurred. Please ensure all input contains valid UTF-8 characters.');
            }
        });
    })->create();
