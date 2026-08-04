<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseAdminTokenCookieForSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            $token = (string) $request->cookies->get('admin_token', '');

            if ($token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
