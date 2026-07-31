<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberHistoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberHistoryController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberHistoryService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        return $this->success('Reading history retrieved.', $this->service->index($request->user(), $page, $perPage));
    }
}
