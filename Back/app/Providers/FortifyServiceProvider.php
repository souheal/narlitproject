<?php

namespace App\Providers;

use App\Services\Auth\LoginService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            return app(LoginService::class)->validateCredentialsForFortify(
                (string) $request->input('email'),
                (string) $request->input('password'),
                $request,
            );
        });

        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);

        RateLimiter::for('auth.register', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
        ]);

        RateLimiter::for('auth.organization.register', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
        ]);

        RateLimiter::for('auth.login', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('auth.otp.verify', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('auth.otp.resend', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('auth.phone_mfa.verify', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('auth.phone_mfa.resend', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('billing.checkout', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('billing.webhook', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);
    }
}
