<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterOrganizationRequest;
use App\Services\Auth\OrganizationRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationRegistrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationRegistrationService $registrationService,
    ) {
    }

    public function store(RegisterOrganizationRequest $request): JsonResponse
    {
        [$user, $profile, $otpPayload] = $this->registrationService->register(
            $request->validated(),
            $request->file('certificate_pdf'),
        );

        $data = [
            'organization' => [
                'public_id' => $profile->public_id,
                'organization_name' => $profile->organization_name,
                'email' => $user->email,
                'tax_id' => $profile->tax_id,
                'irs_verified' => $profile->irs_verified,
                'verification_status' => $profile->verification_status,
            ],
            'otp_expires_at' => $otpPayload['expires_at']->toIso8601String(),
            'next_step' => 'verify_email',
        ];

        return $this->success('Organization registration successful. Please verify your email.', $data, 201);
    }
}
