<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Notifications\SmsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhoneMfaService
{
    public function __construct(
        protected SmsService $smsService,
    ) {
    }

    public function issueForUser(User $user): array
    {
        if ($user->phone === null || $user->phone === '') {
            throw new ApiException('Please enter your phone number.', 422);
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = CarbonImmutable::now()->addMinutes(10);

        $user->forceFill([
            'phone_mfa_code' => Hash::make($code),
            'phone_mfa_expires_at' => $expiresAt,
            'phone_mfa_verified_at' => null,
        ])->save();

        Cache::put($this->previewCacheKey($user), $code, $expiresAt);

        $this->smsService->send($user->phone, "Your NarLit verification code is {$code}.");

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
        ];
    }

    public function verify(User $user, string $code): User
    {
        if ($user->phone_mfa_code === null || $user->phone_mfa_expires_at === null) {
            throw new ApiException('Please request a new phone verification code.', 422);
        }

        if ($user->phone_mfa_expires_at->isPast()) {
            throw new ApiException('The verification code has expired.', 422);
        }

        if (! Hash::check($code, $user->phone_mfa_code)) {
            throw new ApiException('Please enter a valid verification code.', 422);
        }

        return DB::transaction(function () use ($user): User {
            $user->forceFill([
                'phone_mfa_code' => null,
                'phone_mfa_expires_at' => null,
                'phone_mfa_verified_at' => now(),
                'first_login_mfa_completed_at' => $user->first_login_mfa_completed_at ?? now(),
            ])->save();

            Cache::forget($this->previewCacheKey($user));

            return $user->refresh();
        }, 3);
    }

    public function previewForUser(User $user): ?string
    {
        return Cache::get($this->previewCacheKey($user));
    }

    protected function previewCacheKey(User $user): string
    {
        return "narlit:phone-mfa-preview:{$user->id}";
    }
}
