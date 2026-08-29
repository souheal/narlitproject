<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Billing\CheckoutContinuationTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class OtpVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OtpService $otpService,
        protected CheckoutContinuationTokenService $checkoutTokens,
    ) {
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            throw new ApiException('Please enter a valid verification code.', 422);
        }

        $verifiedUser = $this->otpService->verify($user, $request->validated('otp'));
        $organizationProfile = Schema::hasTable('organization_profiles')
            ? $verifiedUser->organizationProfile
            : null;

        if ($organizationProfile !== null) {
            return $this->success('Email verified successfully. Your organization registration is pending review.', [
                'organization' => [
                    'public_id' => $organizationProfile->public_id,
                    'organization_name' => $organizationProfile->organization_name,
                    'email' => $verifiedUser->email,
                    'irs_verified' => $organizationProfile->irs_verified,
                    'verification_status' => $organizationProfile->verification_status,
                ],
                'next_step' => 'pending_review',
            ]);
        }

        $checkoutToken = $this->checkoutTokens->issueForUser($verifiedUser);

        return $this->success('Email verified successfully.', [
            'user' => [
                'public_id' => $verifiedUser->public_id,
                'email' => $verifiedUser->email,
                'email_verified_at' => $verifiedUser->email_verified_at?->toIso8601String(),
                'is_active' => $verifiedUser->is_active,
            ],
            'next_step' => 'checkout',
            'checkout_token' => $checkoutToken,
            'checkout_token_expires_at' => $this->checkoutTokens->expiresAt($verifiedUser)?->toIso8601String(),
        ]);
    }

    public function resend(ResendOtpRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            return $this->neutralResendResponse();
        }

        $this->otpService->issueForUser($user);

        $data = [
            'next_step' => 'verify_email',
        ];

        if (app()->environment('local')) {
            $data['otp'] = $this->otpService->previewForUser($user);
        }

        return $this->success('A new verification code has been sent.', $data);
    }

    protected function neutralResendResponse(): JsonResponse
    {
        return $this->success('A new verification code has been sent.', [
            'next_step' => 'verify_email',
        ]);
    }
}
