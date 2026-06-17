<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService,
    ) {
    }

    public function sendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->sendResetOtp($request->validated('email'));

        $data = [
            'message' => 'Password reset code sent to your email.',
            'next_step' => 'verify_reset_otp',
        ];

        if (app()->environment(['local', 'testing']) && ($result['otp'] ?? null) !== null) {
            $data['otp'] = $result['otp'];
        }

        return response()->json($data);
    }

    public function verifyOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        $this->passwordResetService->verifyResetOtp(
            $request->validated('email'),
            $request->validated('otp'),
        );

        return response()->json([
            'message' => 'Password reset code verified successfully.',
            'next_step' => 'reset_password',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            $request->validated('email'),
            $request->validated('otp'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Password reset successfully.',
            'next_step' => 'login',
        ]);
    }
}
