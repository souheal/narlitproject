<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNarLitUserAccess
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new ApiException('Authentication is required.', 401);
        }

        if ($user->email_verified_at === null) {
            throw new ApiException('Please verify your email before continuing.', 403);
        }

        if (! $user->is_active) {
            throw new ApiException('Please complete your subscription before logging in.', 403);
        }

        if (! $this->subscriptionService->userHasRequiredAccess($user)) {
            throw new ApiException('Please complete your subscription before logging in.', 403);
        }

        return $next($request);
    }
}
