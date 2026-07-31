<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformSettingsService
{
    public const GROUPS = [
        'impact_split',
        'subscription_plans',
        'payout',
        'security',
        'content',
        'email',
    ];

    public function all(): array
    {
        return Cache::remember('platform_settings:all', now()->addMinutes(10), function (): array {
            return collect(self::GROUPS)
                ->mapWithKeys(fn (string $group): array => [$group => $this->group($group)])
                ->all();
        });
    }

    public function group(string $group): array
    {
        $this->ensureKnownGroup($group);

        return Cache::remember("platform_settings:{$group}", now()->addMinutes(10), function () use ($group): array {
            $defaults = $this->defaults()[$group];
            $stored = PlatformSetting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (PlatformSetting $setting): array => [
                    str($setting->key)->after("{$group}.")->toString() => $setting->value['value'] ?? $setting->value,
                ])
                ->all();

            return $this->mergeSettings($defaults, $stored);
        });
    }

    public function update(string $group, array $values, User $admin, Request $request): array
    {
        $this->ensureKnownGroup($group);

        return DB::transaction(function () use ($group, $values, $admin, $request): array {
            $old = $this->group($group);
            $merged = array_replace_recursive($old, $values);
            $types = $this->types()[$group];

            foreach ($merged as $key => $value) {
                PlatformSetting::query()->updateOrCreate(
                    ['key' => "{$group}.{$key}"],
                    [
                        'value' => ['value' => $value],
                        'group' => $group,
                        'type' => $types[$key] ?? gettype($value),
                        'is_public' => false,
                        'updated_by' => $admin->id,
                    ],
                );
            }

            $this->invalidate($group);
            $new = $this->group($group);
            $this->log($admin, $group, $old, $new, $request);

            return $new;
        }, 3);
    }

    public function defaults(): array
    {
        return [
            'impact_split' => [
                'nonprofit_percentage' => (int) config('services.impact.nonprofit_share_percent', 33),
                'operations_percentage' => (int) config('services.impact.operations_share_percent', 33),
                'growth_percentage' => (int) config('services.impact.growth_share_percent', 34),
            ],
            'subscription_plans' => [
                'plans' => [
                    [
                        'key' => 'monthly',
                        'name' => 'Monthly',
                        'billing_interval' => 'monthly',
                        'display_price' => '7.00',
                        'stripe_price_id' => null,
                        'enabled' => true,
                        'founding_member_cap' => null,
                    ],
                    [
                        'key' => 'yearly',
                        'name' => 'Yearly',
                        'billing_interval' => 'yearly',
                        'display_price' => '96.00',
                        'stripe_price_id' => null,
                        'enabled' => true,
                        'founding_member_cap' => null,
                    ],
                ],
            ],
            'payout' => [
                'minimum_payout_amount' => '25.00',
                'payout_day_of_month' => 15,
                'automatic_execution_enabled' => false,
                'retry_attempts' => 3,
                'execution_mode_warning' => 'Live transfers require explicit STRIPE_ENABLE_TRANSFERS=true.',
            ],
            'security' => [
                'email_otp_expiry_minutes' => (int) config('auth.otp_expires_minutes', 10),
                'phone_mfa_expiry_minutes' => 10,
                'login_attempt_limit' => 5,
                'lockout_duration_minutes' => 15,
                'token_expiration_days' => 30,
                'read_rate_limit_per_minute' => 120,
            ],
            'content' => [
                'minimum_reading_seconds' => 30,
                'minimum_scroll_percentage' => (int) config('services.impact.completed_read_percent', 80),
                'one_counted_read_period_hours' => 24,
                'article_approval_required' => true,
                'featured_article_limit' => 5,
            ],
            'email' => [
                'sender_name' => config('app.name', 'NarLit'),
                'sender_address' => 'no-reply@narlit.com',
                'support_email' => 'support@narlit.com',
                'transactional_emails' => [
                    'email_otp' => true,
                    'password_reset' => true,
                    'phone_mfa' => true,
                    'subscription_receipts' => true,
                    'organization_review_updates' => true,
                ],
            ],
        ];
    }

    protected function types(): array
    {
        return [
            'impact_split' => [
                'nonprofit_percentage' => 'integer',
                'operations_percentage' => 'integer',
                'growth_percentage' => 'integer',
            ],
            'subscription_plans' => ['plans' => 'array'],
            'payout' => [
                'minimum_payout_amount' => 'decimal',
                'payout_day_of_month' => 'integer',
                'automatic_execution_enabled' => 'boolean',
                'retry_attempts' => 'integer',
                'execution_mode_warning' => 'string',
            ],
            'security' => [
                'email_otp_expiry_minutes' => 'integer',
                'phone_mfa_expiry_minutes' => 'integer',
                'login_attempt_limit' => 'integer',
                'lockout_duration_minutes' => 'integer',
                'token_expiration_days' => 'integer',
                'read_rate_limit_per_minute' => 'integer',
            ],
            'content' => [
                'minimum_reading_seconds' => 'integer',
                'minimum_scroll_percentage' => 'integer',
                'one_counted_read_period_hours' => 'integer',
                'article_approval_required' => 'boolean',
                'featured_article_limit' => 'integer',
            ],
            'email' => [
                'sender_name' => 'string',
                'sender_address' => 'string',
                'support_email' => 'string',
                'transactional_emails' => 'array',
            ],
        ];
    }

    protected function invalidate(string $group): void
    {
        Cache::forget('platform_settings:all');
        Cache::forget("platform_settings:{$group}");
    }

    protected function mergeSettings(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (
                array_key_exists($key, $defaults)
                && is_array($defaults[$key])
                && is_array($value)
                && array_is_list($defaults[$key])
            ) {
                $defaults[$key] = $value;

                continue;
            }

            if (array_key_exists($key, $defaults) && is_array($defaults[$key]) && is_array($value)) {
                $defaults[$key] = $this->mergeSettings($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    protected function ensureKnownGroup(string $group): void
    {
        if (! in_array($group, self::GROUPS, true)) {
            throw new ApiException('Settings group was not found.', 404);
        }
    }

    protected function log(User $admin, string $group, array $old, array $new, Request $request): void
    {
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'platform_settings.updated',
            'entity_type' => 'platform_settings',
            'entity_id' => $group,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => json_encode([
                'old' => $old,
                'new' => $new,
                'status' => 'success',
            ]),
            'created_at' => now(),
        ]);
    }
}
