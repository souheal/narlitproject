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

    public function index(): JsonResponse
    {
        $impact = $this->settings->group('impact_split');

        return $this->success('Public settings retrieved.', [
            'impact_split' => [
                'nonprofit_percentage' => (int) ($impact['nonprofit_percentage'] ?? 33),
                'operations_percentage' => (int) ($impact['operations_percentage'] ?? 33),
                'growth_percentage' => (int) ($impact['growth_percentage'] ?? 34),
            ],
        ]);
    }
}
