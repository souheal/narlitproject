<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAnalyticsRequest;
use App\Services\Admin\AdminAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAnalyticsController extends Controller
{
    use ApiResponse;

    public function combined(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Analytics retrieved successfully.', [
            'analytics' => $analytics->combined($request),
        ]);
    }
}
