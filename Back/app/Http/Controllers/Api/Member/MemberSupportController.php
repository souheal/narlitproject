<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberSupportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSupportController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberSupportService $service)
    {
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        return $this->success('Support ticket submitted.', $this->service->createTicket($request->user(), $data), 201);
    }
}
