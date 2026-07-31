<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberPreferenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberPreferenceController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberPreferenceService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Preferences retrieved.', $this->service->show($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:80'],
            'followed_organizations' => ['sometimes', 'array'],
            'followed_organizations.*' => ['string', 'max:80'],
            'email_notifications' => ['sometimes', 'boolean'],
            'push_notifications' => ['sometimes', 'boolean'],
            'email_new_articles' => ['sometimes', 'boolean'],
            'email_new_from_supported' => ['sometimes', 'boolean'],
            'email_impact_summary' => ['sometimes', 'boolean'],
            'email_product_updates' => ['sometimes', 'boolean'],
            'push_new_articles' => ['sometimes', 'boolean'],
            'push_achievements' => ['sometimes', 'boolean'],
            'push_payment_events' => ['sometimes', 'boolean'],
            'digest_frequency' => ['sometimes', 'string', 'in:daily,weekly,monthly,never'],
            'email_digest' => ['sometimes', 'string', 'in:off,daily,weekly,monthly'],
            'theme' => ['sometimes', 'string', 'in:light,dark,system'],
            'language' => ['sometimes', 'string', 'max:8'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'monthly_reading_goal' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        return $this->success('Preferences updated.', $this->service->update($request->user(), $data));
    }
}
