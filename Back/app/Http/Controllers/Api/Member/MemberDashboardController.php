<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Impact\UserImpactDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserImpactDashboardService $dashboardService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Member dashboard retrieved.', [
            'dashboard' => $this->dashboardService->dashboard($request->user()),
        ]);
    }
}
