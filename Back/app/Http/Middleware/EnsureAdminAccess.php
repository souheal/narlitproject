<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Authentication is required.', 401);
        }

        if (! $user->isAdminAccount()) {
            throw new ApiException('Admin access is required.', 403);
        }

        if ($user->email_verified_at === null || ! $user->is_active) {
            throw new ApiException('Admin account is not active.', 403);
        }

        if (! $user->hasCompletedMfaEnrollment()) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Administrator MFA enrollment is required.',
                'data' => [
                    'next_step' => 'mfa_enrollment_required',
                ],
            ], 403);
        }

        return $next($request);
    }
}
