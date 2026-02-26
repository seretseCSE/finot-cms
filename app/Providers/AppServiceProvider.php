<?php

namespace App\Providers;

use App\Helpers\EthiopianDateHelper;
use App\Listeners\CleanupUserSession;
use App\Listeners\RecordUserSession;
use App\Models\Member;
use App\Observers\MemberObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EthiopianDateHelper::class, function ($app) {
            return new EthiopianDateHelper();
        });

        // Register FilamentServiceProvider (when Filament is installed)
        $this->app->register(\App\Providers\FilamentServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Log authentication attempts
        Auth::viaRequest('phone', function ($request) {
            Log::info('Phone authentication attempt', [
                'ip' => $request->ip(),
                'phone' => $request->get('phone'),
                'password_provided' => !empty($request->get('password')),
                'user_agent' => $request->userAgent(),
            ]);
            return !empty($request->get('phone'));
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
        // Set default string length for MySQL compatibility
        Schema::defaultStringLength(191);

        // Register event listeners for session tracking
        \Event::listen(Login::class, RecordUserSession::class);
        \Event::listen(Logout::class, CleanupUserSession::class);

        Member::observe(MemberObserver::class);

        // Extend authentication to support phone numbers
        Auth::provider('eloquent', function ($app, $config) {
            return new class($app['hash'], $config['model']) extends \Illuminate\Auth\EloquentUserProvider {
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

        // Register Carbon macro for Ethiopian date conversion
        Carbon::macro('ethiopian', function () {
            /** @var Carbon $this */
            $helper = app(EthiopianDateHelper::class);
            return $helper->toEthiopian($this);
        });

        // Register Carbon macro for Ethiopian date string
        Carbon::macro('ethiopianString', function () {
            /** @var Carbon $this */
            $helper = app(EthiopianDateHelper::class);
            return $helper->toString($this);
        });
    }
}
