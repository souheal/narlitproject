<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ((string) $this->route('group')) {
            'impact_split' => [
                'nonprofit_percentage' => ['required', 'integer', 'min:0', 'max:100'],
                'operations_percentage' => ['required', 'integer', 'min:0', 'max:100'],
                'growth_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            ],
            'subscription_plans' => [
                'plans' => ['required', 'array', 'min:1', 'max:10'],
                'plans.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_\\-]+$/'],
                'plans.*.name' => ['required', 'string', 'max:120'],
                'plans.*.billing_interval' => ['required', 'in:monthly,yearly'],
                'plans.*.display_price' => ['required', 'numeric', 'min:0'],
                'plans.*.stripe_price_id' => ['nullable', 'string', 'regex:/^price_[A-Za-z0-9_]+$/'],
                'plans.*.enabled' => ['required', 'boolean'],
                'plans.*.founding_member_cap' => ['nullable', 'integer', 'min:1'],
            ],
            'payout' => [
                'minimum_payout_amount' => ['required', 'numeric', 'min:0'],
                'payout_day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
                'automatic_execution_enabled' => ['required', 'boolean'],
                'retry_attempts' => ['required', 'integer', 'min:0', 'max:10'],
                'execution_mode_warning' => ['sometimes', 'string', 'max:255'],
            ],
            'security' => [
                'email_otp_expiry_minutes' => ['required', 'integer', 'min:1', 'max:120'],
                'phone_mfa_expiry_minutes' => ['required', 'integer', 'min:1', 'max:120'],
                'login_attempt_limit' => ['required', 'integer', 'min:1', 'max:20'],
                'lockout_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'token_expiration_days' => ['required', 'integer', 'min:1', 'max:365'],
                'read_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            ],
            'content' => [
                'minimum_reading_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
                'minimum_scroll_percentage' => ['required', 'integer', 'min:1', 'max:100'],
                'one_counted_read_period_hours' => ['required', 'integer', 'min:1', 'max:8760'],
                'article_approval_required' => ['required', 'boolean'],
                'featured_article_limit' => ['required', 'integer', 'min:0', 'max:100'],
            ],
            'email' => [
                'sender_name' => ['required', 'string', 'max:120'],
                'sender_address' => ['required', 'email', 'max:255'],
                'support_email' => ['required', 'email', 'max:255'],
                'transactional_emails' => ['required', 'array'],
                'transactional_emails.email_otp' => ['required', 'boolean'],
                'transactional_emails.password_reset' => ['required', 'boolean'],
                'transactional_emails.phone_mfa' => ['required', 'boolean'],
                'transactional_emails.subscription_receipts' => ['required', 'boolean'],
                'transactional_emails.organization_review_updates' => ['required', 'boolean'],
            ],
            default => [
                '_group' => ['required'],
            ],
        };
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ((string) $this->route('group') !== 'impact_split') {
                    return;
                }

                $total = (int) $this->input('nonprofit_percentage')
                    + (int) $this->input('operations_percentage')
                    + (int) $this->input('growth_percentage');

                if ($total !== 100) {
                    $validator->errors()->add('total', 'Donation split percentages must total 100%.');
                }
            },
        ];
    }
}
