<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Services\Auth\OtpService;
use App\Services\Auth\RegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RegistrationService $registrationService,
        protected OtpService $otpService,
    ) {
    }

    public function store(RegisterUserRequest $request): JsonResponse
    {
        [$user, $otpPayload] = $this->registrationService->register($request->validated());

        $data = [
            'user' => [
                'public_id' => $user->public_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'is_active' => $user->is_active,
            ],
            'otp_expires_at' => $otpPayload['expires_at']->toIso8601String(),
        ];

        if (app()->environment('local')) {
            $data['otp'] = $this->otpService->previewForUser($user);
        }

        $data['next_step'] = 'verify_email';

        return $this->success('Registration successful. Please verify your email.', $data, 201);
    }
}
