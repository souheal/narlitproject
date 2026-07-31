<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicApi\PublicOrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOrganizationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PublicOrganizationService $organizations,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            'Organizations retrieved.',
            $this->organizations->paginate($request),
        );
    }

    public function show(string $publicId): JsonResponse
    {
        return $this->success(
            'Organization retrieved.',
            $this->organizations->show($publicId),
        );
    }
}
