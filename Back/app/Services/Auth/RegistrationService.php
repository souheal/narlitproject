<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        protected OtpService $otpService,
    ) {
    }

    public function register(array $data): array
    {
        try {
            return DB::transaction(function () use ($data): array {
                $roleId = $this->resolveUserRoleId();

                $user = User::create([
                    'public_id' => (string) Str::uuid(),
                    'role_id' => $roleId,
                    'full_name' => $data['full_name'],
                    'username' => $this->generateUsername($data['email']),
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'is_active' => false,
                    'failed_login_attempts' => 0,
                ]);

                Cache::put($this->planCacheKey($user->id), $data['subscription_plan'] ?? 'monthly', now()->addDay());

                $otpPayload = $this->otpService->issueForUser($user);

                return [$user->refresh(), $otpPayload];
            }, 3);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists.'],
            ]);
        }
    }

    public function planCacheKey(int $userId): string
    {
        return "narlit:registration-plan:{$userId}";
    }

    protected function resolveUserRoleId(): int
    {
        $existingRoleId = DB::table('roles')->where('name', 'user')->value('id');

        if ($existingRoleId !== null) {
            return (int) $existingRoleId;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function generateUsername(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->substr(0, 40)
            ->value();

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $tail = '_'.$suffix++;
            $username = Str::limit($base, 50 - strlen($tail), '').$tail;
        }

        return $username;
    }
}
