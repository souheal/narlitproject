<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PublicOrganizationService;
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
        return $this->success('Organizations retrieved successfully.', $this->organizations->paginate(
            $request,
            $request->user('sanctum'),
        ));
    }

    public function show(Request $request, string $organization): JsonResponse
    {
        return $this->success('Organization retrieved successfully.', [
            'organization' => $this->organizations->find($organization, $request->user('sanctum')),
        ]);
    }
}
