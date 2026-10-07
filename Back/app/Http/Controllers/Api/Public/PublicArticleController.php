<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PublicArticleService;
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

    public function index(Request $request): JsonResponse
    {
        return $this->success('Articles retrieved successfully.', $this->articles->paginate($request));
    }

    public function show(Request $request, string $article): JsonResponse
    {
        return $this->success('Article retrieved successfully.', $this->articles->detail(
            $article,
            $request->user('sanctum'),
        ));
    }
}
