<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberSubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberSubscriptionService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Subscription retrieved.', $this->service->show($request->user()));
    }

    public function changePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        return $this->success('Plan updated.', $this->service->changePlan($request->user(), $data['plan']));
    }

    public function cancel(Request $request): JsonResponse
    {
        return $this->success('Subscription canceled.', $this->service->cancel($request->user()));
    }

    public function resume(Request $request): JsonResponse
    {
        return $this->success('Subscription resumed.', $this->service->resume($request->user()));
    }

    public function updatePaymentMethod(Request $request): JsonResponse
    {
        return $this->success('Payment method session created.', $this->service->updatePaymentMethod($request->user()));
    }
}
