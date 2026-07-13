<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminUserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        /** @var Subscription|null $latestSubscription */
        $latestSubscription = $user->getRelation('latestSubscriptionForAdmin');

        return [
            'profile' => [
                'public_id' => $user->public_id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->getAttribute('role_name'),
                'email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone_mfa_completed' => $user->first_login_mfa_completed_at !== null,
                'phone_mfa_completed_at' => $user->first_login_mfa_completed_at?->toIso8601String(),
                'account_status' => $user->is_active ? 'active' : 'suspended',
                'is_active' => $user->is_active,
                'registered_at' => $user->created_at?->toIso8601String(),
            ],
            'subscription' => $latestSubscription ? [
                'public_id' => $latestSubscription->public_id,
                'plan' => $latestSubscription->plan,
                'amount' => number_format((float) $latestSubscription->amount, 2, '.', ''),
                'currency' => $latestSubscription->currency,
                'status' => $latestSubscription->status,
                'started_at' => $latestSubscription->started_at?->toIso8601String(),
                'expires_at' => $latestSubscription->expires_at?->toIso8601String(),
                'canceled_at' => $latestSubscription->canceled_at?->toIso8601String(),
            ] : null,
            'payment_summary' => [
                'total_paid' => number_format((float) $user->getAttribute('paid_payments_sum'), 2, '.', ''),
                'paid_count' => (int) $user->getAttribute('paid_payments_count'),
                'failed_count' => (int) $user->getAttribute('failed_payments_count'),
                'recent_payments' => $user->paymentsForAdmin->map(fn (Payment $payment): array => [
                    'public_id' => $payment->public_id,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                ])->all(),
            ],
            'read_impact_summary' => [
                'total_reads' => (int) $user->getAttribute('article_reads_count'),
                'completed_reads' => (int) $user->getAttribute('completed_article_reads_count'),
                'total_points' => (int) $user->getAttribute('article_reads_points_sum'),
                'total_impact_amount' => number_format((float) $user->getAttribute('impact_amount_sum'), 2, '.', ''),
                'organizations_supported' => (int) $user->getAttribute('organizations_supported_count'),
            ],
            'recent_login' => [
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
                'locked_until' => $user->locked_until?->toIso8601String(),
                'failed_login_attempts' => $user->failed_login_attempts,
            ],
            'admin_action_history' => $user->getAttribute('admin_action_history')
                ->map(fn (object $log): array => [
                    'action' => $log->action,
                    'admin_name' => $log->admin_name,
                    'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
