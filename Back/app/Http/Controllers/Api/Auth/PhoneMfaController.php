<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendPhoneMfaRequest;
use App\Http\Requests\Auth\VerifyPhoneMfaRequest;
use App\Models\User;
use App\Services\Admin\AdminSecurityAuditService;
use App\Services\Auth\LoginService;
use App\Services\Auth\PhoneMfaService;
use App\Services\Billing\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class PhoneMfaController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PhoneMfaService $phoneMfaService,
        protected LoginService $loginService,
        protected SubscriptionService $subscriptionService,
    ) {}

    public function verify(VerifyPhoneMfaRequest $request): JsonResponse
    {
        $user = $this->resolveEligibleUser($request->validated('email'), true);

        try {
            $result = $this->loginService->completePhoneMfa($user, $request->validated('code'), $request);
        } catch (ApiException $exception) {
            if ($user->isAdminAccount()) {
                app(AdminSecurityAuditService::class)->log($user, 'auth.admin_mfa_failed', $request, [
                    'status' => 'failure',
                    'reason' => $this->safeMfaFailureReason($exception),
                ]);
            }

            throw $exception;
        }

        $isAdmin = $this->loginService->isAdmin($result['user']);

        $data = [
            'user' => [
                'public_id' => $result['user']->public_id,
                'full_name' => $result['user']->full_name,
                'username' => $result['user']->username,
                'email' => $result['user']->email,
            ],
            'token' => $isAdmin ? null : $result['token'],
            'token_type' => 'Bearer',
            'next_step' => 'completed',
        ];

        if ($isAdmin) {
            $data['mfa_enrolled'] = $result['user']->hasCompletedMfaEnrollment();
        }

        $response = $this->success($isAdmin ? 'Phone verification completed.' : 'Login successful.', $data);

        if ($isAdmin) {
            app(AdminSecurityAuditService::class)->log($result['user'], 'auth.admin_mfa_completed', $request, [
                'status' => 'success',
            ]);

            $response->withCookie($this->adminTokenCookie($result['token']));
        }

        return $response;
    }

    public function resend(ResendPhoneMfaRequest $request): JsonResponse
    {
        $user = $this->resolveEligibleUser($request->validated('email'), false);

        if ($user === null) {
            return $this->neutralResendResponse();
        }

        if (! $user->isAdminAccount() && $user->first_login_mfa_completed_at !== null) {
            return $this->neutralResendResponse();
        }

        $mfaPayload = $this->phoneMfaService->issueForUser($user);

        if ($user->isAdminAccount()) {
            app(AdminSecurityAuditService::class)->log($user, 'auth.admin_mfa_resent', $request, [
                'status' => 'success',
            ]);
        }

        $data = [
            'next_step' => 'phone_mfa_required',
        ];

        if (app()->environment('local')) {
            $data['mfa_code'] = $mfaPayload['code'];
        }

        return $this->success('A new phone verification code has been sent.', $data);
    }

    protected function resolveEligibleUser(string $email, bool $forVerification): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->neutralFailure($forVerification);
        }

        if ($user->email_verified_at === null) {
            return $this->neutralFailure($forVerification);
        }

        if ($user->isAdminAccount()) {
            if (! $user->is_active) {
                return $this->neutralFailure($forVerification);
            }

            return $user;
        }

        if (! $user->is_active || ! $this->subscriptionService->userHasRequiredAccess($user)) {
            return $this->neutralFailure($forVerification);
        }

        return $user;
    }

    protected function neutralFailure(bool $forVerification): ?User
    {
        if ($forVerification) {
            throw new ApiException('Please enter a valid verification code.', 422);
        }

        return null;
    }

    protected function neutralResendResponse(): JsonResponse
    {
        return $this->success('A new phone verification code has been sent.', [
            'next_step' => 'phone_mfa_required',
        ]);
    }

    protected function safeMfaFailureReason(ApiException $exception): string
    {
        return match ($exception->getMessage()) {
            'The verification code has expired.' => 'expired_code',
            'Please request a new phone verification code.' => 'missing_or_used_code',
            default => 'invalid_code',
        };
    }

    protected function adminTokenCookie(string $token): Cookie
    {
        return cookie()->make(
            name: 'admin_token',
            value: $token,
            minutes: $this->loginService->adminTokenTtlMinutes(),
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }
}
