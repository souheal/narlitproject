<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicApi\PublicArticleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicArticleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublicArticleService $articles,
    ) {
    }

    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 3);

        return $this->success(
            'Featured articles retrieved.',
            $this->articles->featured($limit),
        );
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        // This endpoint is public (guests see the paywall preview), but if a
        // bearer token is attached we resolve the user through Sanctum so
        // active subscribers get the full article.
        $user = $request->user() ?? auth('sanctum')->user();

        return $this->success(
            'Article retrieved.',
            $this->articles->show($publicId, $user),
        );
    }
}
