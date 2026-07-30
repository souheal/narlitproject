<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'public_id' => $user->public_id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->getAttribute('role_name'),
            'subscription_status' => $user->getAttribute('subscription_status') ?? 'inactive',
            'email_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'mfa_completed' => $user->first_login_mfa_completed_at !== null,
            'phone_mfa_completed_at' => $user->first_login_mfa_completed_at?->toIso8601String(),
            'account_status' => $user->is_active ? 'active' : 'suspended',
            'is_active' => $user->is_active,
            'articles_read' => (int) ($user->getAttribute('completed_article_reads_count') ?? 0),
            'total_impact' => $this->decimalString($user->getAttribute('impact_amount_sum')),
            'registered_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    protected function decimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $string = (string) $value;

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $string)) {
            return '0.00';
        }

        [$whole, $decimal] = array_pad(explode('.', $string, 2), 2, '');

        return $whole.'.'.str_pad(substr($decimal, 0, 2), 2, '0');
    }
}
