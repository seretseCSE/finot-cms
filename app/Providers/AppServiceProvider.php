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
use App\Models\Document;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\StudentEnrollment;
use App\Models\Tour;
use App\Observers\AidDistributionObserver;
use App\Observers\AttendanceSessionObserver;
use App\Observers\ContributionObserver;
use App\Observers\DepartmentObserver;
use App\Observers\DocumentObserver;
use App\Observers\MemberObserver;
use App\Observers\RehearsalObserver;
use App\Observers\StudentEnrollmentObserver;
use App\Observers\TourObserver;
use App\Services\BackupCreationService;
use App\Services\BackupRestorationService;
use App\Services\BackupCleanupService;
use App\Services\Contracts\BackupCreationServiceInterface;
use App\Services\Contracts\BackupRestorationServiceInterface;
use App\Services\Contracts\BackupCleanupServiceInterface;
use App\Services\Contracts\OfflineSyncServiceInterface;
use App\Services\Contracts\PushNotificationServiceInterface;
use App\Services\OfflineSyncService;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Register backup services
        $this->app->singleton(BackupCreationServiceInterface::class, function ($app) {
            return new BackupCreationService();
        });

        $this->app->singleton(BackupRestorationServiceInterface::class, function ($app) {
            return new BackupRestorationService();
        });

        $this->app->singleton(BackupCleanupServiceInterface::class, function ($app) {
            return new BackupCleanupService();
        });

        // Register sync service
        $this->app->singleton(OfflineSyncServiceInterface::class, function ($app) {
            return new OfflineSyncService();
        });

        // Register notification service
        $this->app->singleton(PushNotificationServiceInterface::class, function ($app) {
            return new PushNotificationService();
        });

        // Register FilamentServiceProvider (when Filament is installed)
        $this->app->register(\App\Providers\FilamentServiceProvider::class);

        // Override default Filament login response to handle password change and API redirects
        $this->app->bind(LoginResponseContract::class, CustomLoginResponse::class);

        // SMS gateway — swap driver via SMS_DRIVER env
        $this->app->singleton(\App\Contracts\SmsGateway::class, function ($app) {
            return match (config('finot.sms.driver')) {
                'yegnatele' => new \App\Services\Sms\YegnaTeleGateway(),
                default     => new \App\Services\Sms\NullGateway(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Set default string length for MySQL compatibility
        Schema::defaultStringLength(191);

        // Register Blade directive for sanitized HTML output
        \Illuminate\Support\Facades\Blade::directive('sanitize', function (string $expression) {
            return "<?php echo \App\Services\HtmlSanitizer::clean($expression); ?>";
        });

        // Register event listeners for session tracking
        \Event::listen(Login::class, RecordUserSession::class);
        \Event::listen(Logout::class, CleanupUserSession::class);

        Member::observe(MemberObserver::class);
        StudentEnrollment::observe(StudentEnrollmentObserver::class);
        Tour::observe(TourObserver::class);
        Department::observe(DepartmentObserver::class);
        AttendanceSession::observe(AttendanceSessionObserver::class);
        AidDistribution::observe(AidDistributionObserver::class);
        Rehearsal::observe(RehearsalObserver::class);
        Contribution::observe(ContributionObserver::class);
        Document::observe(DocumentObserver::class);

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

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-browse', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('public-search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('public-write', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('public-lookup', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
