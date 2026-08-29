<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Admin\AdminSecurityAuditService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class LoginService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PhoneMfaService $phoneMfaService,
    ) {}

    public function attempt(string $email, string $password, Request $request): array
    {
        $email = strtolower($email);
        $limiterKey = $this->limiterKey($email, $request);

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                $this->lockUser($user);
            }

            throw new ApiException('Too many failed login attempts. Please try again later.', 423);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            if ($user !== null) {
                $this->recordFailedAttempt($user);

                if ($user->isAdminAccount()) {
                    app(AdminSecurityAuditService::class)->log($user, 'auth.admin_login_failed', $request, [
                        'status' => 'failure',
                        'reason' => 'invalid_credentials',
                    ]);
                }
            }

            RateLimiter::hit($limiterKey, 900);

            throw new ApiException('Invalid email or password.', 422);
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            throw new ApiException('Your account is temporarily locked.', 423);
        }

        if ($user->email_verified_at === null) {
            throw new ApiException('Please verify your email before continuing.', 403);
        }

        $isAdmin = $user->isAdminAccount();

        if ($isAdmin) {
            if (! $user->is_active) {
                throw new ApiException('Admin account is not active.', 403);
            }
        } else {
            if (! $user->is_active) {
                throw new ApiException('Please complete your subscription before logging in.', 403);
            }

            if (! $this->subscriptionService->userHasRequiredAccess($user)) {
                throw new ApiException('Please complete your subscription before logging in.', 403);
            }
        }

        RateLimiter::clear($limiterKey);

        if ($isAdmin && ($user->phone === null || $user->phone === '')) {
            app(AdminSecurityAuditService::class)->log($user, 'auth.admin_login_failed', $request, [
                'status' => 'failure',
                'reason' => 'missing_phone',
            ]);

            throw new ApiException('Phone verification is required for administrator accounts. Please contact support.', 403);
        }

        if ($isAdmin || $user->requiresMfa()) {
            $mfaPayload = $this->phoneMfaService->issueForUser($user);

            if ($isAdmin) {
                app(AdminSecurityAuditService::class)->log($user, 'auth.admin_login_password_verified', $request, [
                    'status' => 'success',
                    'next_step' => 'phone_mfa_required',
                ]);
            }

            return [
                'status' => 'phone_mfa_required',
                'user' => $user->refresh(),
                'mfa' => $mfaPayload,
            ];
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return [
            'status' => 'completed',
            'user' => $user->refresh(),
            'token' => $this->createToken($user),
        ];
    }

    public function logout(Request $request): void
    {
        $request->user()?->currentAccessToken()?->delete();
    }

    public function refreshAdminToken(User $admin, Request $request): array
    {
        if (! $admin->isAdminAccount()) {
            throw new ApiException('Admin access is required.', 403);
        }

        if ($admin->email_verified_at === null || ! $admin->is_active) {
            throw new ApiException('Admin account is not active.', 403);
        }

        if (! $admin->hasCompletedMfaEnrollment()) {
            throw new ApiException('Administrator MFA enrollment is required.', 403, [
                'next_step' => 'mfa_enrollment_required',
            ]);
        }

        return DB::transaction(function () use ($admin, $request): array {
            $currentToken = $admin->currentAccessToken();

            if (! $currentToken instanceof PersonalAccessToken) {
                throw new ApiException('Authentication is required.', 401);
            }

            $lockedToken = PersonalAccessToken::query()
                ->whereKey($currentToken->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedToken === null || ($lockedToken->expires_at !== null && $lockedToken->expires_at->isPast())) {
                throw new ApiException('Authentication is required.', 401);
            }

            $lockedToken->delete();

            $plainTextToken = $this->createToken($admin);

            $this->recordAdminRefresh($admin, $request);

            return [
                'token' => $plainTextToken,
                'expires_in_minutes' => $this->adminTokenTtlMinutes(),
            ];
        });
    }

    public function completePhoneMfa(User $user, string $code, Request $request): array
    {
        $verifiedUser = $this->phoneMfaService->verify($user, $code);

        $verifiedUser->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return [
            'user' => $verifiedUser->refresh(),
            'token' => $this->createToken($verifiedUser),
        ];
    }

    public function validateCredentialsForFortify(string $email, string $password, Request $request): ?User
    {
        try {
            $result = $this->attempt($email, $password, $request);

            return $result['user'] ?? null;
        } catch (ApiException) {
            return null;
        }
    }

    protected function limiterKey(string $email, Request $request): string
    {
        return sprintf('narlit-login:%s|%s', $email, $request->ip());
    }

    protected function recordFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null,
        ])->save();
    }

    protected function lockUser(User $user): void
    {
        $user->forceFill([
            'failed_login_attempts' => max(5, $user->failed_login_attempts),
            'locked_until' => now()->addMinutes(15),
        ])->save();
    }

    public function adminTokenTtlMinutes(): int
    {
        return max(1, (int) config('auth.admin_token_ttl', 240));
    }

    public function userTokenTtlMinutes(): int
    {
        return max(1, (int) config('auth.user_token_ttl', 43200));
    }

    public function isAdmin(User $user): bool
    {
        return $user->isAdminAccount();
    }

    protected function tokenTtlMinutes(User $user): int
    {
        return $this->isAdmin($user)
            ? $this->adminTokenTtlMinutes()
            : $this->userTokenTtlMinutes();
    }

    protected function createToken(User $user): string
    {
        return $user->createToken(
            'web',
            ['*'],
            now()->addMinutes($this->tokenTtlMinutes($user)),
        )->plainTextToken;
    }

    protected function recordAdminRefresh(User $admin, Request $request): void
    {
        if (! Schema::hasTable('admin_logs')) {
            return;
        }

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'auth.session_refreshed',
            'entity_type' => 'user',
            'entity_id' => $admin->public_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => json_encode([
                'expires_in_minutes' => $this->adminTokenTtlMinutes(),
            ]),
            'created_at' => now(),
        ]);
    }
}
