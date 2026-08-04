<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        if (app()->runningUnitTests() && ! config('permission.enforce_in_tests', false)) {
            return $next($request);
        }

        if (! Schema::hasTable('permissions')) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'errors' => [],
            ], 403);
        }

        $user = $request->user();
        $permissionList = explode('|', $permissions);

        if ($user !== null && $user->canAny($permissionList)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You do not have permission to perform this action.',
            'errors' => [],
        ], 403);
    }
}
