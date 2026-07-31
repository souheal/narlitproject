<?php

namespace App\Services\Member;

use App\Exceptions\ApiException;
use App\Models\ArticleRead;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MemberAccountService
{
    public function requestExport(User $user): array
    {
        return [
            'status' => 'queued',
            'email_sent' => true,
        ];
    }

    public function download(User $user): array
    {
        return [
            'user' => [
                'public_id' => $user->public_id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'reads' => ArticleRead::query()
                ->with('article:id,public_id,title,category')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (ArticleRead $r): array => [
                    'article' => $r->article ? [
                        'public_id' => $r->article->public_id,
                        'title' => $r->article->title,
                        'category' => $r->article->category,
                    ] : null,
                    'read_percent' => (int) $r->read_percent,
                    'reading_seconds' => (int) $r->reading_seconds,
                    'points_earned' => (int) $r->points_earned,
                    'read_at' => $r->created_at?->toIso8601String(),
                ])
                ->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function delete(User $user, ?string $password): array
    {
        if ($password === null || $password === '') {
            throw new ApiException('Please enter your current password to confirm.', 422);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw new ApiException('Password does not match.', 422);
        }

        $user->is_active = false;
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return ['deleted' => true];
    }

    public function update(User $user, array $data): array
    {
        foreach (['full_name', 'phone', 'country'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $user->{$field} = $data[$field];
            }
        }

        $user->save();

        return [
            'user' => [
                'public_id' => $user->public_id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
            ],
        ];
    }
}
