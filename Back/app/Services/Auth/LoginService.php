<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PhoneMfaService $phoneMfaService,
    ) {
    }

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

        if (! $user->is_active) {
            throw new ApiException('Please complete your subscription before logging in.', 403);
        }

        if (! $this->subscriptionService->userHasActiveSubscription($user)) {
            throw new ApiException('Please complete your subscription before logging in.', 403);
        }

        RateLimiter::clear($limiterKey);

        if ($user->first_login_mfa_completed_at === null) {
            $mfaPayload = $this->phoneMfaService->issueForUser($user);

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

    protected function createToken(User $user): string
    {
        return $user->createToken('narlit-user-token')->plainTextToken;
    }
}
