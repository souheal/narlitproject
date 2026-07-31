<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicApi\PublicSearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublicSearchService $search,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            'Search results retrieved.',
            $this->search->search($request),
        );
    }
}
