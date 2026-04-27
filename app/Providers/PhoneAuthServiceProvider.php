<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PhoneAuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any authentication services.
     */
    public function boot(): void
    {
        // Log authentication attempts
        Auth::viaRequest('phone', function ($request) {
            Log::info('Phone authentication attempt', [
                'ip' => $request->ip(),
                'phone' => $request->get('phone'),
                'password_provided' => ! empty($request->get('password')),
                'user_agent' => $request->userAgent(),
            ]);

            return ! empty($request->get('phone'));
        });

        // Log when user is authenticated
        Auth::resolved(function ($auth) {
            if ($auth instanceof \Illuminate\Auth\SessionGuard) {
                $auth->authenticated(function ($user) {
                    Log::info('User authenticated successfully', [
                        'user_id' => $user->id,
                        'phone' => $user->phone,
                        'name' => $user->name,
                        'ip' => request()->ip(),
                    ]);
                });
            }
        });

        // Log authentication failures
        Auth::resolved(function ($auth) {
            if ($auth instanceof \Illuminate\Auth\SessionGuard) {
                $auth->failed(function ($user, $credentials) {
                    Log::warning('Authentication failed', [
                        'user_found' => $user ? true : false,
                        'user_id' => $user?->id,
                        'phone_attempted' => $credentials['phone'] ?? 'unknown',
                        'ip' => request()->ip(),
                    ]);
                });
            }
        });

        // Extend authentication to support phone numbers
        Auth::provider('eloquent', function ($app, $config) {
            return new class ($app['hash'], $config['model']) extends \Illuminate\Auth\EloquentUserProvider {
                public function retrieveByCredentials(array $credentials)
                {
                    // For phone-only authentication, we expect 'phone' field
                    $phone = $credentials['phone'] ?? null;

                    Log::info('Custom auth provider retrieving user by phone', [
                        'phone' => $phone,
                        'credentials_keys' => array_keys($credentials),
                    ]);

                    if (empty($phone)) {
                        Log::warning('No phone provided in credentials');

                        return null;
                    }

                    $model = $this->createModel();

                    $user = $model->newQuery()
                        ->where('phone', $phone)
                        ->first();

                    Log::info('User lookup result', [
                        'phone' => $phone,
                        'user_found' => $user ? true : false,
                        'user_id' => $user?->id,
                    ]);

                    return $user;
                }
            };
        });
    }
}
