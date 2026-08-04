<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendPhoneMfaRequest;
use App\Http\Requests\Auth\VerifyPhoneMfaRequest;
use App\Models\User;
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
        $user = $this->resolveEligibleUser($request->validated('email'));
        $result = $this->loginService->completePhoneMfa($user, $request->validated('code'), $request);

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
            $response->withCookie($this->adminTokenCookie($result['token']));
        }

        return $response;
    }

    public function resend(ResendPhoneMfaRequest $request): JsonResponse
    {
        $user = $this->resolveEligibleUser($request->validated('email'));

        if (! $user->isAdminAccount() && $user->first_login_mfa_completed_at !== null) {
            throw new ApiException('Phone verification has already been completed.', 409);
        }

        $mfaPayload = $this->phoneMfaService->issueForUser($user);

        $data = [
            'next_step' => 'phone_mfa_required',
            'phone_mfa_expires_at' => $mfaPayload['expires_at']->toIso8601String(),
        ];

        if (app()->environment('local')) {
            $data['mfa_code'] = $mfaPayload['code'];
        }

        return $this->success('A new phone verification code has been sent.', $data);
    }

    protected function resolveEligibleUser(string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new ApiException('No account was found for the provided email address.', 404);
        }

        if ($user->email_verified_at === null) {
            throw new ApiException('Please verify your email before continuing.', 403);
        }

        if ($user->isAdminAccount()) {
            if (! $user->is_active) {
                throw new ApiException('Admin account is not active.', 403);
            }

            return $user;
        }

        if (! $user->is_active || ! $this->subscriptionService->userHasRequiredAccess($user)) {
            throw new ApiException('Please complete your subscription before logging in.', 403);
        }

        return $user;
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
