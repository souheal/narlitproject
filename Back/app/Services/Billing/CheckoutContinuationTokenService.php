<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutContinuationTokenService
{
    private const TOKEN_BYTES = 32;

    private const EXPIRES_MINUTES = 20;

    public function issueForUser(User $user): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $user->forceFill([
            'checkout_token_hash' => $this->digest($token),
            'checkout_token_expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'checkout_token_consumed_at' => null,
            'checkout_replay_message' => null,
            'checkout_replay_data' => null,
        ])->save();

        return $token;
    }

    public function reserveForCheckout(string $email, ?string $token): ?array
    {
        $token = trim((string) $token);

        if ($token === '' || ! $this->hasExpectedShape($token)) {
            return null;
        }

        $digest = $this->digest($token);

        return DB::transaction(function () use ($email, $digest): ?array {
            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $this->tokenAllowsCheckout($user, $digest)) {
                return null;
            }

            if ($this->hasBeenConsumed($user)) {
                $cached = $this->cachedCheckoutResponse($user);

                return $cached === null ? null : [
                    'message' => (string) $cached['message'],
                    'data' => (array) $cached['data'],
                    'replayed' => true,
                ];
            }

            $user->forceFill([
                'checkout_token_consumed_at' => now(),
            ])->save();

            return [
                'user' => $user->refresh(),
                'reserved' => true,
            ];
        }, 3);
    }

    private function hasBeenConsumed(User $user): bool
    {
        return $user->checkout_token_consumed_at !== null;
    }

    private function cachedCheckoutResponse(User $user): ?array
    {
        $cached = [
            'message' => $user->checkout_replay_message,
            'data' => $user->checkout_replay_data,
        ];

        return is_string($cached['message']) && is_array($cached['data']) ? $cached : null;
    }

    public function storeCheckoutResponse(User $user, string $message, array $checkout): void
    {
        DB::transaction(function () use ($user, $message, $checkout): void {
            User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->forceFill([
                    'checkout_replay_message' => $message,
                    'checkout_replay_data' => $checkout,
                ])->save();
        }, 3);
    }

    public function releaseCheckoutReservation(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->checkout_replay_message !== null || $lockedUser->checkout_replay_data !== null) {
                return;
            }

            $lockedUser->forceFill([
                'checkout_token_consumed_at' => null,
            ])->save();
        }, 3);
    }

    public function expiresAt(User $user): ?Carbon
    {
        return $user->checkout_token_expires_at;
    }

    private function hasExpectedShape(string $token): bool
    {
        return Str::length($token) === self::TOKEN_BYTES * 2
            && ctype_xdigit($token);
    }

    private function tokenAllowsCheckout(?User $user, string $digest): bool
    {
        if ($user === null || $user->checkout_token_hash === null || $user->checkout_token_expires_at === null) {
            return false;
        }

        if ($user->checkout_token_expires_at->isPast() || $user->email_verified_at === null) {
            return false;
        }

        return hash_equals($user->checkout_token_hash, $digest);
    }

    private function digest(string $token): string
    {
        return hash_hmac('sha256', $token, $this->key());
    }

    private function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        if ($key === '') {
            throw new RuntimeException('Application key is required for checkout continuation tokens.');
        }

        return $key;
    }
}
