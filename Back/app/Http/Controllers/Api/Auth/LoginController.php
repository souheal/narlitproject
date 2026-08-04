<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoginService $loginService,
    ) {}

    public function store(LoginUserRequest $request): JsonResponse
    {
        $result = $this->loginService->attempt(
            $request->validated('email'),
            $request->validated('password'),
            $request,
        );

        $user = $result['user'];

        if ($result['status'] === 'phone_mfa_required') {
            $data = [
                'next_step' => 'phone_mfa_required',
                'phone_mfa_expires_at' => $result['mfa']['expires_at']->toIso8601String(),
            ];

            if (app()->environment('local')) {
                $data['mfa_code'] = $result['mfa']['code'];
            }

            return $this->success('Phone verification is required.', $data);
        }

        $isAdmin = $this->loginService->isAdmin($user);

        $response = $this->success('Login successful.', [
            'user' => [
                'public_id' => $user->public_id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
            ],
            'token' => $isAdmin ? null : $result['token'],
            'token_type' => 'Bearer',
            'next_step' => 'completed',
        ]);

        if ($isAdmin) {
            $response->withCookie($this->adminTokenCookie($result['token']));
        }

        return $response;
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->loginService->logout($request);

        return $this->success('Logout successful.')
            ->withCookie($this->expiredAdminTokenCookie());
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

    protected function expiredAdminTokenCookie(): Cookie
    {
        return cookie()->make(
            name: 'admin_token',
            value: '',
            minutes: -240,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }
}
