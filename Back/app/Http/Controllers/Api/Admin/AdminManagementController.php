<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class AdminManagementController extends Controller
{
    use ApiResponse;

    private const ADMIN_ROLES = [
        'super_admin',
        'admin_finance',
        'admin_content',
        'admin_users',
        'admin_settings',
        'admin_readonly',
    ];

    public function index(): JsonResponse
    {
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::ADMIN_ROLES))
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get()
            ->map(fn (User $user): array => $this->adminPayload($user))
            ->values();

        return $this->success('Admins retrieved successfully.', [
            'admins' => $admins,
        ]);
    }

    public function show(string $publicId): JsonResponse
    {
        return $this->success('Admin retrieved successfully.', [
            'admin' => $this->adminPayload($this->findAdmin($publicId)),
        ]);
    }

    public function updateRoles(string $publicId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(self::ADMIN_ROLES)],
        ]);

        $target = $this->findAdmin($publicId);
        $roles = array_values(array_unique($validated['roles']));

        if ($target->id === $request->user()->id && ! $request->user()->hasRole('super_admin')) {
            throw new ApiException('You cannot escalate your own admin privileges.', 422);
        }

        return DB::transaction(function () use ($request, $target, $roles): JsonResponse {
            $oldRoles = $target->safeAdminRoleNames();

            if (in_array('super_admin', $oldRoles, true) && ! in_array('super_admin', $roles, true) && $this->activeSuperAdminCount() <= 1) {
                throw new ApiException('You cannot remove the last active super admin.', 422);
            }

            $target->syncRoles($roles);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::table('admin_logs')->insert([
                'admin_id' => $request->user()->id,
                'action' => 'admin.roles_updated',
                'entity_type' => 'user',
                'entity_id' => $target->public_id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'metadata' => json_encode([
                    'old_roles' => $oldRoles,
                    'new_roles' => $roles,
                ]),
                'created_at' => now(),
            ]);

            return $this->success('Admin roles updated successfully.', [
                'admin' => $this->adminPayload($target->refresh()),
            ]);
        }, 3);
    }

    private function findAdmin(string $publicId): User
    {
        $user = User::query()->where('public_id', $publicId)->first();

        if ($user === null || ! $user->isAdminAccount()) {
            throw new ApiException('Admin was not found.', 404);
        }

        return $user;
    }

    private function activeSuperAdminCount(): int
    {
        return User::role('super_admin')
            ->where('is_active', true)
            ->whereNotNull('mfa_enrolled_at')
            ->count();
    }

    private function adminPayload(User $user): array
    {
        return [
            'public_id' => $user->public_id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->safeAdminRoleNames(),
            'permissions' => $user->safeAdminPermissionNames(),
        ];
    }
}
