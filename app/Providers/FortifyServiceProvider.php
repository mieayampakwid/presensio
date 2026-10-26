<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\ScannerLoginResponse;
use App\Http\Responses\ScannerPasskeyLoginResponse;
use App\Http\Responses\ScannerTwoFactorLoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, ScannerLoginResponse::class);
        $this->app->bind(TwoFactorLoginResponse::class, ScannerTwoFactorLoginResponse::class);
        $this->app->bind(PasskeyLoginResponse::class, ScannerPasskeyLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('username', $request->string('username')->toString())->first();
            $password = $request->string('password')->toString();

            // Password first: the deactivation message must only be visible
            // to someone who holds valid credentials, not to anyone probing
            // usernames.
            $this->app->bind(LoginResponse::class, ScannerLoginResponse::class);
            $this->app->bind(TwoFactorLoginResponse::class, ScannerTwoFactorLoginResponse::class);
            $this->app->bind(PasskeyLoginResponse::class, ScannerPasskeyLoginResponse::class);
            // Unknown usernames hash a throwaway value so every attempt runs
            // the same bcrypt round — response latency must not reveal
            // whether an account exists.
            if (! Hash::check($password, $user->password ?? Hash::make($password))) {
                return null;
            }

            if ($user === null) {
                return null;
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'username' => 'Your account has been deactivated. Please contact the school administrator.',
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => true,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
