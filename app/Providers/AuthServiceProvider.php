<?php

namespace App\Providers;

use App\Auth\PhoneUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom phone user provider
        Auth::provider('phone', function ($app, array $config) {
            return new PhoneUserProvider($app['hash'], $config['model']);
        });
    }
}
