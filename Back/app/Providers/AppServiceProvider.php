<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ProductionSecurityGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        app(ProductionSecurityGuard::class)->validate();

        $this->configureAdminRateLimiters();

        Gate::before(function (User $user, string $ability): ?bool {
            if (
                $user->is_active
                && $user->hasCompletedMfaEnrollment()
                && $user->hasRole('super_admin')
            ) {
                return true;
            }

            return null;
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function configureAdminRateLimiters(): void
    {
        RateLimiter::for('admin.read', fn (Request $request): array => [
            Limit::perMinute($this->adminLimit('read_per_minute', 120))
                ->by('admin-read:'.$this->adminRateLimitIdentity($request)),
        ]);

        RateLimiter::for('admin.destructive', fn (Request $request): array => [
            Limit::perMinute($this->adminLimit('destructive_per_minute', 10))
                ->by('admin-destructive:'.$this->adminRateLimitIdentity($request)),
        ]);

        RateLimiter::for('admin.audit.export', fn (Request $request): array => [
            Limit::perHour($this->adminLimit('audit_export_per_hour', 3))
                ->by('admin-audit-export:'.$this->adminRateLimitIdentity($request)),
        ]);

        RateLimiter::for('admin.analytics', fn (Request $request): array => [
            Limit::perMinute($this->adminLimit('analytics_per_minute', 30))
                ->by('admin-analytics:'.$this->adminRateLimitIdentity($request)),
        ]);

        RateLimiter::for('admin.settings.update', fn (Request $request): array => [
            Limit::perMinute($this->adminLimit('settings_update_per_minute', 5))
                ->by('admin-settings-update:'.$this->adminRateLimitIdentity($request)),
        ]);
    }

    private function adminLimit(string $key, int $default): int
    {
        return max(1, (int) config("security.admin_rate_limits.{$key}", $default));
    }

    private function adminRateLimitIdentity(Request $request): string
    {
        $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();

        return $identity.'|'.$request->ip();
    }
}
