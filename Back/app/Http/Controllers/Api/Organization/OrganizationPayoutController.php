<?php

namespace App\Http\Controllers\Api\Organization;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Organization\OrganizationPayoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationPayoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationPayoutService $payoutService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()?->organizationProfile;

        if ($organization === null) {
            throw new ApiException('No organization profile found for this user.', 403);
        }

        return $this->success('Payouts retrieved.', $this->payoutService->overview($organization));
    }
}
