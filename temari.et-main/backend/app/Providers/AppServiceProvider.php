<?php

namespace App\Providers;

use App\Ai\GeminiSchemaCompatGateway;
use App\Models\User;
use App\Services\Chat\ConversationAccess;
use App\Services\CheckEt\CheckEtClient;
use App\Services\CheckEt\HttpCheckEtClient;
use App\Services\Sms\SmsClient;
use App\Services\Sms\TiltekSmsClient;
use App\Support\DateFormatter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\GeminiProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsClient::class, TiltekSmsClient::class);
        $this->app->bind(CheckEtClient::class, HttpCheckEtClient::class);
        // One memoized chat access kernel per request (ADR-019).
        $this->app->singleton(ConversationAccess::class);

        // Gemini instances ride our schema-compat gateway (strips the
        // `additionalProperties` keyword Gemini's tool schema rejects).
        $this->app->afterResolving(AiManager::class, function (AiManager $ai): void {
            $ai->extend('gemini', fn (Application $app, array $config): GeminiProvider => new GeminiProvider(
                new GeminiSchemaCompatGateway($app->make(Dispatcher::class)),
                $config,
                $app->make(Dispatcher::class),
            ));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // N+1 tripwire: lazy relation loads throw outside production so they
        // surface in tests/staging instead of as slow pages in production.
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Calendar/clock display modes are memoized per process. Clear the memo
        // before each queued job so a long-running worker picks up a school's
        // settings change instead of serving the modes it first resolved.
        Queue::before(fn () => DateFormatter::flushMemo());
    }
}
