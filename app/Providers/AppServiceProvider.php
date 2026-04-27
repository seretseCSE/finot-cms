<?php

namespace App\Providers;

use App\Helpers\EthiopianDateHelper;
use App\Http\Responses\Auth\CustomLoginResponse;
use App\Listeners\CleanupUserSession;
use App\Listeners\RecordUserSession;
use App\Models\AidDistribution;
use App\Models\AttendanceSession;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Tour;
use App\Observers\AidDistributionObserver;
use App\Observers\AttendanceSessionObserver;
use App\Observers\ContributionObserver;
use App\Observers\DepartmentObserver;
use App\Observers\MemberObserver;
use App\Observers\RehearsalObserver;
use App\Observers\TourObserver;
use Carbon\Carbon;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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

        // Override default Filament login response to handle password change and API redirects
        $this->app->bind(LoginResponseContract::class, CustomLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL compatibility
        Schema::defaultStringLength(191);

        // Register event listeners for session tracking
        \Event::listen(Login::class, RecordUserSession::class);
        \Event::listen(Logout::class, CleanupUserSession::class);

        Member::observe(MemberObserver::class);
        Tour::observe(TourObserver::class);
        Department::observe(DepartmentObserver::class);
        AttendanceSession::observe(AttendanceSessionObserver::class);
        AidDistribution::observe(AidDistributionObserver::class);
        Rehearsal::observe(RehearsalObserver::class);
        Contribution::observe(ContributionObserver::class);

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
