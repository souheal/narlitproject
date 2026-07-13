<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function show(Request $request, AdminDashboardService $dashboard): JsonResponse
    {
        $period = (string) $request->query('period', 'last_30_days');

        if (! in_array($period, ['last_7_days', 'last_30_days', 'last_90_days', 'current_year'], true)) {
            throw new ApiException('Please choose a valid dashboard period.', 422);
        }

        return $this->success('Admin dashboard retrieved successfully.', $dashboard->dashboard($period));
    }
}
