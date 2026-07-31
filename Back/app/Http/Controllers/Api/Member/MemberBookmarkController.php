<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberBookmarkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberBookmarkController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberBookmarkService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success('Bookmarks retrieved.', $this->service->list($request->user()));
    }

    public function store(Request $request, string $articlePublicId): JsonResponse
    {
        return $this->success('Bookmark created.', $this->service->create($request->user(), $articlePublicId), 201);
    }

    public function destroy(Request $request, string $articlePublicId): JsonResponse
    {
        return $this->success('Bookmark removed.', $this->service->delete($request->user(), $articlePublicId));
    }
}
