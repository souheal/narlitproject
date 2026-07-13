<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPayoutActionRequest;
use App\Http\Requests\Admin\AdminPayoutIndexRequest;
use App\Http\Requests\Admin\GeneratePayoutBatchRequest;
use App\Http\Resources\Admin\AdminPayoutBatchDetailResource;
use App\Http\Resources\Admin\AdminPayoutBatchResource;
use App\Http\Resources\Admin\AdminPayoutItemResource;
use App\Services\Admin\AdminPayoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPayoutController extends Controller
{
    use ApiResponse;

    public function summary(AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout summary retrieved successfully.', $payouts->summary());
    }

    public function index(AdminPayoutIndexRequest $request, AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout batches retrieved successfully.', [
            'payout_batches' => AdminPayoutBatchResource::collection($payouts->paginate($request))->response()->getData(true),
        ]);
    }

    public function show(string $publicId, AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout batch retrieved successfully.', [
            'payout_batch' => new AdminPayoutBatchDetailResource($payouts->details($publicId)),
        ]);
    }

    public function generate(GeneratePayoutBatchRequest $request, AdminPayoutService $payouts): JsonResponse
    {
        $result = $payouts->generate(
            $request->user(),
            (string) $request->validated('month'),
            (bool) $request->boolean('preview'),
            $request,
        );

        if ($result['preview']) {
            return $this->success('Payout batch preview calculated successfully.', [
                'calculation' => $result['calculation'],
            ]);
        }

        return $this->success('Payout batch generated successfully.', [
            'payout_batch' => new AdminPayoutBatchDetailResource($result['batch']),
        ], 201);
    }

    public function execute(string $publicId, AdminPayoutActionRequest $request, AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout batch execution queued successfully.', [
            'payout_batch' => new AdminPayoutBatchResource($payouts->execute($request->user(), $publicId, $request)),
        ]);
    }

    public function retryItem(int $id, AdminPayoutActionRequest $request, AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout item retry queued successfully.', [
            'payout_item' => new AdminPayoutItemResource($payouts->retryItem($request->user(), $id, $request)),
        ]);
    }

    public function cancel(string $publicId, AdminPayoutActionRequest $request, AdminPayoutService $payouts): JsonResponse
    {
        return $this->success('Payout batch canceled successfully.', [
            'payout_batch' => new AdminPayoutBatchDetailResource($payouts->cancel($request->user(), $publicId, $request)),
        ]);
    }
}
