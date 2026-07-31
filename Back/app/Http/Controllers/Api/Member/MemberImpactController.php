<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberImpactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberImpactController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberImpactService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success('Impact summary retrieved.', $this->service->show($request->user()));
    }
}
