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

    public function show(Request $request, string $publicId): JsonResponse
    {
        return $this->success(
            'Article retrieved.',
            $this->articles->show($publicId, $request->user()),
        );
    }
}
