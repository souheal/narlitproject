<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserActionRequest;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Services\Admin\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function index(Request $request, AdminUserService $users): JsonResponse
    {
        return $this->success('Users retrieved successfully.', [
            'users' => AdminUserResource::collection($users->paginate($request))->response()->getData(true),
        ]);
    }

    public function show(string $publicId, AdminUserService $users): JsonResponse
    {
        return $this->success('User retrieved successfully.', [
            'user' => new AdminUserDetailResource($users->details($publicId)),
        ]);
    }

    public function suspend(string $publicId, AdminUserActionRequest $request, AdminUserService $users): JsonResponse
    {
        $user = $users->updateStatus($request->user(), $publicId, false, $request);

        return $this->success('User suspended successfully.', [
            'user' => new AdminUserResource($users->details($user->public_id)),
        ]);
    }

    public function activate(string $publicId, AdminUserActionRequest $request, AdminUserService $users): JsonResponse
    {
        $user = $users->updateStatus($request->user(), $publicId, true, $request);

        return $this->success('User activated successfully.', [
            'user' => new AdminUserResource($users->details($user->public_id)),
        ]);
    }

    public function sendPasswordReset(string $publicId, AdminUserActionRequest $request, AdminUserService $users): JsonResponse
    {
        $users->sendPasswordReset($request->user(), $publicId, $request);

        return $this->success('Password reset email sent successfully.');
    }

    public function resetMfa(string $publicId, AdminUserActionRequest $request, AdminUserService $users): JsonResponse
    {
        $user = $users->resetMfa($request->user(), $publicId, $request);

        return $this->success('Phone MFA reset successfully.', [
            'user' => new AdminUserResource($users->details($user->public_id)),
        ]);
    }

    public function revokeTokens(string $publicId, AdminUserActionRequest $request, AdminUserService $users): JsonResponse
    {
        $count = $users->revokeTokens($request->user(), $publicId, $request);

        return $this->success('User tokens revoked successfully.', [
            'tokens_revoked' => $count,
        ]);
    }
}
