<?php

namespace App\Providers;

use App\Auth\PhoneUserProvider;
use App\Models\Contribution;
use App\Models\InventoryItem;
use App\Models\Tour;
use App\Models\User;
use App\Policies\ContributionPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\TourPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Contribution::class => ContributionPolicy::class,
        InventoryItem::class => InventoryItemPolicy::class,
        Tour::class => TourPolicy::class,
        User::class => UserPolicy::class,
    ];

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
