<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                'plans.*.billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
                'plans.*.display_price' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
                'plans.*.stripe_price_id' => ['nullable', 'string', 'regex:/^price_[A-Za-z0-9]+$/'],
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
                match ((string) $this->route('group')) {
                    'impact_split' => $this->validateImpactSplit($validator),
                    'subscription_plans' => $this->validateSubscriptionPlans($validator),
                    default => null,
                };
            },
        ];
    }

    public function messages(): array
    {
        return [
            'plans.required' => 'At least one subscription plan is required.',
            'plans.array' => 'Subscription plans must be submitted as an array.',
            'plans.min' => 'At least one subscription plan is required.',
            'plans.*.key.required' => 'Each subscription plan must have a key.',
            'plans.*.key.regex' => 'Subscription plan keys may only contain lowercase letters, numbers, underscores, and hyphens.',
            'plans.*.name.required' => 'Each subscription plan must have a name.',
            'plans.*.billing_interval.required' => 'The billing interval must be monthly or yearly.',
            'plans.*.billing_interval.in' => 'The billing interval must be monthly or yearly.',
            'plans.*.display_price.required' => 'Please enter a valid subscription plan price.',
            'plans.*.display_price.regex' => 'Please enter a valid subscription plan price.',
            'plans.*.stripe_price_id.regex' => 'The Stripe Price ID must be a valid Stripe price identifier.',
            'plans.*.enabled.required' => 'Each subscription plan must define whether it is enabled.',
            'plans.*.enabled.boolean' => 'Each subscription plan enabled value must be true or false.',
        ];
    }

    protected function validateImpactSplit(Validator $validator): void
    {
        $total = (int) $this->input('nonprofit_percentage')
            + (int) $this->input('operations_percentage')
            + (int) $this->input('growth_percentage');

        if ($total !== 100) {
            $validator->errors()->add('total', 'Donation split percentages must total 100%.');
        }
    }

    protected function validateSubscriptionPlans(Validator $validator): void
    {
        $plans = $this->input('plans', []);

        if (! is_array($plans) || $plans === []) {
            return;
        }

        $enabledCount = 0;
        $keys = [];
        $stripePriceIds = [];

        foreach ($plans as $index => $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $enabled = $plan['enabled'] ?? null;

            if (! is_bool($enabled) && ! in_array($enabled, [0, 1], true)) {
                $validator->errors()->add("plans.{$index}.enabled", 'Each subscription plan enabled value must be true or false.');
            }

            if ($this->enabledValue($enabled)) {
                $enabledCount++;

                if (! is_string($plan['stripe_price_id'] ?? null) || trim((string) $plan['stripe_price_id']) === '') {
                    $validator->errors()->add("plans.{$index}.stripe_price_id", 'An enabled subscription plan must have a Stripe Price ID.');
                }
            }

            $key = $plan['key'] ?? null;

            if (is_string($key)) {
                if (isset($keys[$key])) {
                    $validator->errors()->add("plans.{$index}.key", 'Each subscription plan must have a unique key.');
                }

                $keys[$key] = true;
            }

            $stripePriceId = $plan['stripe_price_id'] ?? null;

            if (is_string($stripePriceId) && trim($stripePriceId) !== '') {
                if (isset($stripePriceIds[$stripePriceId])) {
                    $validator->errors()->add("plans.{$index}.stripe_price_id", 'Each subscription plan must use a unique Stripe Price ID.');
                }

                $stripePriceIds[$stripePriceId] = true;
            }
        }

        if ($enabledCount === 0) {
            $validator->errors()->add('plans', 'At least one subscription plan must be enabled.');
        }
    }

    protected function enabledValue(mixed $value): bool
    {
        return $value === true || $value === 1;
    }
}
