<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSubscriptionIndexRequest;
use App\Http\Requests\Admin\AdminSubscriptionSummaryRequest;
use App\Http\Requests\Admin\CancelSubscriptionRequest;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Http\Requests\Admin\RefundSubscriptionRequest;
use App\Http\Resources\Admin\AdminPaymentResource;
use App\Http\Resources\Admin\AdminSubscriptionDetailResource;
use App\Http\Resources\Admin\AdminSubscriptionResource;
use App\Services\Admin\AdminSubscriptionRevenueService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminSubscriptionRevenueController extends Controller
{
    use ApiResponse;

    public function summary(AdminSubscriptionSummaryRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Subscription revenue summary retrieved successfully.', $subscriptions->summary($request));
    }

    public function metrics(AdminSubscriptionSummaryRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Subscription metrics retrieved successfully.', [
            'metrics' => $subscriptions->metrics(),
        ]);
    }

    public function refundSubscription(string $publicId, RefundSubscriptionRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Latest payment refunded successfully.', [
            'payment' => new AdminPaymentResource($subscriptions->refundLatestForSubscription(
                $request->user(),
                $publicId,
                (string) ($request->validated('reason') ?? 'Full refund issued by admin.'),
                $request,
            )),
        ]);
    }

    public function index(AdminSubscriptionIndexRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Subscriptions retrieved successfully.', [
            'subscriptions' => AdminSubscriptionResource::collection($subscriptions->paginate($request))->response()->getData(true),
        ]);
    }

    public function show(string $publicId, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Subscription retrieved successfully.', [
            'subscription' => new AdminSubscriptionDetailResource($subscriptions->details($publicId)),
        ]);
    }

    public function cancel(string $publicId, CancelSubscriptionRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Subscription canceled successfully.', [
            'subscription' => new AdminSubscriptionResource($subscriptions->cancel($request->user(), $publicId, $request)->loadMissing('user')),
        ]);
    }

    public function refund(string $publicId, RefundPaymentRequest $request, AdminSubscriptionRevenueService $subscriptions): JsonResponse
    {
        return $this->success('Payment refunded successfully.', [
            'payment' => new AdminPaymentResource($subscriptions->refund($request->user(), $publicId, $request->validated('reason'), $request)),
        ]);
    }
}
