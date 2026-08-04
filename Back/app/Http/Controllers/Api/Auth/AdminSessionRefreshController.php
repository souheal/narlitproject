<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AdminSessionRefreshController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoginService $loginService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $result = $this->loginService->refreshAdminToken($request->user(), $request);

        return $this->success('Admin session refreshed successfully.', [
            'expires_in_minutes' => $result['expires_in_minutes'],
        ])->withCookie($this->adminTokenCookie($result['token']));
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
