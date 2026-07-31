<?php

namespace App\Http\Controllers\Api\Organization;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Organization\OrganizationDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationDashboardService $dashboardService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user?->organizationProfile;

        if ($organization === null) {
            throw new ApiException('No organization profile found for this user.', 403);
        }

        return $this->success('Organization dashboard retrieved.', [
            'dashboard' => $this->dashboardService->dashboard($user, $organization),
        ]);
    }
}
