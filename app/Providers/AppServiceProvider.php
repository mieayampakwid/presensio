<?php

namespace App\Providers;

use App\Authorization\RolePermissions;
use App\Enums\Ability;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\PeriodService;
use App\Services\Finance\Preconditions\GuardianDepositReconciliationPrecondition;
use App\Services\Finance\Preconditions\ReceivablesReconciliationPrecondition;
use App\Services\Finance\Preconditions\TrialBalanceClosePrecondition;
use App\Services\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Memoizes the settings row per process (spec 03).
        $this->app->singleton(SchoolSettings::class);

        $this->app->bind(PeriodService::class, function ($app) {
            $service = new PeriodService(
                $app->make(AuditLogger::class),
                $app->make(TrialBalanceClosePrecondition::class),
            );
            $service->addPrecondition($app->make(ReceivablesReconciliationPrecondition::class));
            $service->addPrecondition($app->make(GuardianDepositReconciliationPrecondition::class));

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => $user->hasAnyRole(...RolePermissions::rolesFor($ability)));
        }

        if ($this->app->runningInConsole() && ($_SERVER['LARAVEL_SAIL'] ?? $_ENV['LARAVEL_SAIL'] ?? false)) {
            DevCommands::artisan('serve', 'server');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Surface hidden N+1 queries as loud failures in dev/test instead
        // of silent extra queries in production.
        Model::preventLazyLoading(! app()->isProduction());

        // Scanner tap endpoint (spec 03) — the framework `api` group ships
        // no default throttle, so the named limiter is load-bearing.
        RateLimiter::for('web-scanner', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()->getAuthIdentifier()));

        RateLimiter::for('scanner', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
