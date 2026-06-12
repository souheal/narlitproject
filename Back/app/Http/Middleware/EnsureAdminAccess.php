<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Authentication is required.', 401);
        }

        $roleName = DB::table('roles')->where('id', $user->role_id)->value('name');

        if ($roleName !== 'admin') {
            throw new ApiException('Admin access is required.', 403);
        }

        if ($user->email_verified_at === null || ! $user->is_active) {
            throw new ApiException('Admin account is not active.', 403);
        }

        return $next($request);
    }
}
