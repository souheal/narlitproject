<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OtpVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OtpService $otpService,
    ) {
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            throw new ApiException('No registration was found for the provided email address.', 404);
        }

        $verifiedUser = $this->otpService->verify($user, $request->validated('otp'));

        return $this->success('Email verified successfully.', [
            'user' => [
                'public_id' => $verifiedUser->public_id,
                'email' => $verifiedUser->email,
                'email_verified_at' => $verifiedUser->email_verified_at?->toIso8601String(),
                'is_active' => $verifiedUser->is_active,
            ],
            'next_step' => 'payment',
        ]);
    }

    public function resend(ResendOtpRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            throw new ApiException('No registration was found for the provided email address.', 404);
        }

        $otpPayload = $this->otpService->issueForUser($user);

        $data = [
            'otp_expires_at' => $otpPayload['expires_at']->toIso8601String(),
        ];

        if (app()->environment('local')) {
            $data['otp'] = $this->otpService->previewForUser($user);
        }

        $data['next_step'] = 'verify_email';

        return $this->success('A new verification code has been sent.', $data);
    }
}
