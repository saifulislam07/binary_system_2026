<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Package;
use App\Support\Money;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
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
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn (Request $request) => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'price', 'bv_value'])
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'price' => Money::format($package->price),
                ]),
            // Referral links: /register?ref=MBR-100001
            'sponsorCode' => is_string($ref = $request->query('ref')) ? strtoupper($ref) : null,
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Applied by App\Http\Middleware\ThrottleAuthEndpoints (Fortify has no hook for these).
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(5)->by('register|'.$request->ip()),
            Limit::perHour(20)->by('register-hour|'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('reset|'.$request->ip()),
            Limit::perHour(10)->by('reset-email|'.Str::lower((string) $request->input('email'))),
        ]);
    }
}
