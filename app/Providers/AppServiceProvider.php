<?php

namespace App\Providers;

use App\Services\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
