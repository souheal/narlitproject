<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $user->forceFill([
            'full_name' => $data['full_name'],
            'username' => $data['username'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => isset($data['country']) ? strtoupper($data['country']) : null,
        ])->save();

        return $this->success('Profile updated successfully.', [
            'user' => $user->refresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw new ApiException('Current password is incorrect.', 422);
        }

        $user->forceFill(['password' => $data['password']])->save();

        return $this->success('Password updated successfully.', [
            'user' => $user->refresh(),
        ]);
    }
}
