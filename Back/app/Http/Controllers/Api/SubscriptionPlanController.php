<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\PublicSubscriptionPlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublicSubscriptionPlanService $subscriptionPlans,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success('Subscription plans retrieved successfully.', [
            'plans' => $this->subscriptionPlans->enabledPlans(),
        ]);
    }
}
