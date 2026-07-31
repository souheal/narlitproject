<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberOnboardingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberOnboardingController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberOnboardingService $service)
    {
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organizations' => ['sometimes', 'array'],
            'organizations.*' => ['string', 'max:80'],
            'follows' => ['sometimes', 'array'],
            'follows.*' => ['string', 'max:80'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:80'],
            'causes' => ['sometimes', 'array'],
            'causes.*' => ['string', 'max:80'],
            'monthly_reading_goal' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'email_digest' => ['sometimes', 'string', 'in:off,daily,weekly,monthly'],
            'push_achievements' => ['sometimes', 'boolean'],
        ]);

        return $this->success('Onboarding completed.', $this->service->complete($request->user(), $data));
    }
}
