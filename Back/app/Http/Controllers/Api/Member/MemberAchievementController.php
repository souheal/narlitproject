<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberAchievementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberAchievementController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberAchievementService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success('Achievements retrieved.', $this->service->list($request->user()));
    }
}
