<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PlatformSettingsService $settings,
    ) {
    }

    /**
     * Exposes only the settings the marketing site needs. Every other group
     * (payout, security, email, ...) stays behind the admin routes.
     */
    public function show(): JsonResponse
    {
        return $this->success('Public settings retrieved.', [
            'impact_split' => $this->settings->group('impact_split'),
        ]);
    }
}
