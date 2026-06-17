<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Auth\SendPasswordResetOtpNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class PasswordResetService
{
    public function sendResetOtp(string $email): array
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return [
                'sent' => false,
                'otp' => null,
                'expires_at' => null,
            ];
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = CarbonImmutable::now()->addMinutes(10);

        DB::transaction(function () use ($user, $otp, $expiresAt): void {
            $user->forceFill([
                'password_reset_otp_code' => Hash::make($otp),
                'password_reset_otp_expires_at' => $expiresAt,
                'password_reset_otp_verified_at' => null,
            ])->save();

            Cache::put($this->previewCacheKey($user), $otp, $expiresAt);

            if (! app()->environment('local')) {
                $user->notify((new SendPasswordResetOtpNotification($otp, $expiresAt))->onQueue('mail'));
            }
        }, 3);

        return [
            'sent' => true,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyResetOtp(string $email, string $otp): bool
    {
        $user = $this->findUserForReset($email);

        DB::transaction(function () use ($user, $otp): void {
            $this->ensureValidOtp($user->refresh(), $otp);

            $user->forceFill([
                'password_reset_otp_verified_at' => now(),
            ])->save();
        }, 3);

        return true;
    }

    public function resetPassword(string $email, string $otp, string $password): void
    {
        $user = $this->findUserForReset($email);

        try {
            DB::transaction(function () use ($user, $otp, $password): void {
                $this->ensureValidOtp($user->refresh(), $otp);

                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_reset_otp_code' => null,
                    'password_reset_otp_expires_at' => null,
                    'password_reset_otp_verified_at' => null,
                    'remember_token' => null,
                ])->save();

                $user->tokens()->delete();
                Cache::forget($this->previewCacheKey($user));
            }, 3);
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ApiException('Password reset failed. Please try again.', 500);
        }
    }

    protected function findUserForReset(string $email): User
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();

        if ($user === null) {
            throw new ApiException('The verification code is invalid.', 422);
        }

        return $user;
    }

    protected function ensureValidOtp(User $user, string $otp): void
    {
        if ($user->password_reset_otp_code === null || $user->password_reset_otp_expires_at === null) {
            throw new ApiException('The verification code is invalid.', 422);
        }

        if ($user->password_reset_otp_expires_at->isPast()) {
            throw new ApiException('The verification code has expired.', 422);
        }

        if (! Hash::check($otp, $user->password_reset_otp_code)) {
            throw new ApiException('The verification code is invalid.', 422);
        }
    }

    protected function previewCacheKey(User $user): string
    {
        return "narlit:password-reset-otp-preview:{$user->id}";
    }
}
