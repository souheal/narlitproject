<?php

namespace App\Http\Controllers\Api\Organization;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Organization\OrganizationStripeConnectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationStripeConnectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationStripeConnectService $stripeConnectService,
    ) {
    }

    public function onboarding(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        $result = $this->stripeConnectService->beginOnboarding($organization);

        return $this->success('Stripe Connect onboarding link generated.', [
            'onboarding' => $result,
            'onboarding_url' => $result['onboarding_url'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        return $this->success('Stripe Connect status retrieved.', [
            'status' => $this->stripeConnectService->status($organization),
        ]);
    }

    protected function currentOrganization(Request $request)
    {
        $organization = $request->user()?->organizationProfile;

        if ($organization === null) {
            throw new ApiException('No organization profile found for this user.', 403);
        }

        return $organization;
    }
}
