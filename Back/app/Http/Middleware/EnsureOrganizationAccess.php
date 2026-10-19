<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Authentication is required.', 401);
        }

        if (DB::table('roles')->where('id', $user->role_id)->value('name') !== 'organization') {
            throw new ApiException('Organization access is required.', 403);
        }

        if ($user->email_verified_at === null || ! $user->is_active) {
            throw new ApiException('Organization account is not active.', 403);
        }

        if ($user->organizationProfile?->verification_status !== 'approved') {
            throw new ApiException('Organization approval is required before managing articles.', 403);
        }

        return $next($request);
    }
}
