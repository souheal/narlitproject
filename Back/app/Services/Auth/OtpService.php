<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Auth\SendOtpNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function issueForUser(User $user): array
    {
        $otp = (string) random_int(100000, 999999);
        $expiresAt = CarbonImmutable::now()->addMinutes(max(1, (int) config('auth.otp_expires_minutes', 10)));

        $user->forceFill([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => $expiresAt,
            'email_verified_at' => null,
            'is_active' => false,
        ])->save();

        Cache::put($this->previewCacheKey($user), $otp, $expiresAt);

        if (! app()->environment('local')) {
            $user->notify((new SendOtpNotification($otp, $expiresAt))->onQueue('mail'));
        }

        return [
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ];
    }

    public function verify(User $user, string $otp): User
    {
        if ($user->otp_code === null || $user->otp_expires_at === null) {
            throw new ApiException('Please enter a valid verification code.', 422);
        }

        if ($user->otp_expires_at->isPast()) {
            throw new ApiException('The verification code has expired.', 422);
        }

        if (! Hash::check($otp, $user->otp_code)) {
            throw new ApiException('Please enter a valid verification code.', 422);
        }

        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }

    public function previewForUser(User $user): ?string
    {
        return Cache::get($this->previewCacheKey($user));
    }

    protected function previewCacheKey(User $user): string
    {
        return "narlit:otp-preview:{$user->id}";
    }
}
