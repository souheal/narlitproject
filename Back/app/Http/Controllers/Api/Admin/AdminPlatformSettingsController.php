<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformSettingsRequest;
use App\Http\Resources\Admin\PlatformSettingsResource;
use App\Services\Admin\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminPlatformSettingsController extends Controller
{
    use ApiResponse;

    public function index(PlatformSettingsService $settings): JsonResponse
    {
        return $this->success('Platform settings retrieved successfully.', [
            'settings' => new PlatformSettingsResource($settings->all()),
        ]);
    }

    public function show(string $group, PlatformSettingsService $settings): JsonResponse
    {
        return $this->success('Platform settings group retrieved successfully.', [
            'group' => $group,
            'settings' => new PlatformSettingsResource($settings->group($group)),
        ]);
    }

    public function update(string $group, UpdatePlatformSettingsRequest $request, PlatformSettingsService $settings): JsonResponse
    {
        return $this->success('Platform settings updated successfully.', [
            'group' => $group,
            'settings' => new PlatformSettingsResource($settings->update($group, $request->validated(), $request->user(), $request)),
        ]);
    }
}
