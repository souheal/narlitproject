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

    /**
     * Single aggregated payload used by the admin analytics screen.
     */
    public function combined(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Analytics retrieved successfully.', [
            'analytics' => $analytics->combined($request),
        ]);
    }

    // The endpoints below expose the individual datasets. They share the same
    // query parameters as `combined` and let a caller pull one slice at a time.

    public function overview(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Analytics overview retrieved successfully.', $analytics->overview($request));
    }

    public function timeseries(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Analytics timeseries retrieved successfully.', $analytics->timeseries($request));
    }

    public function topOrganizations(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Top organizations retrieved successfully.', $analytics->topOrganizations($request));
    }

    public function topArticles(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Top articles retrieved successfully.', $analytics->topArticles($request));
    }

    public function categories(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Category analytics retrieved successfully.', $analytics->categories($request));
    }

    public function funnel(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Conversion funnel retrieved successfully.', $analytics->funnel($request));
    }

    public function cohorts(AdminAnalyticsRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return $this->success('Cohort analytics retrieved successfully.', $analytics->cohorts($request));
    }
}
