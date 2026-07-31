<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberExploreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberExploreController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberExploreService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success('Explore feed retrieved.', $this->service->index($request->user()));
    }
}
