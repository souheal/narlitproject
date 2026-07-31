<?php

namespace App\Services\Billing;

use App\Services\Admin\PlatformSettingsService;

class PublicSubscriptionPlanService
{
    public function __construct(
        protected PlatformSettingsService $settings,
    ) {}

    public function enabledPlans(): array
    {
        $settings = $this->settings->group('subscription_plans');
        $plans = $settings['plans'] ?? [];

        if (! is_array($plans)) {
            return [];
        }

        return collect($plans)
            ->filter(fn (mixed $plan): bool => is_array($plan) && ($plan['enabled'] ?? false) === true)
            ->map(fn (array $plan): array => $this->publicPlan($plan))
            ->filter(fn (array $plan): bool => $plan !== [])
            ->values()
            ->all();
    }

    protected function publicPlan(array $plan): array
    {
        foreach (['key', 'name', 'billing_interval', 'display_price'] as $field) {
            if (! array_key_exists($field, $plan) || ! is_scalar($plan[$field])) {
                return [];
            }
        }

        $stripePriceId = $plan['stripe_price_id'] ?? null;

        if ($stripePriceId !== null && ! is_scalar($stripePriceId)) {
            return [];
        }

        return [
            'key' => (string) $plan['key'],
            'name' => (string) $plan['name'],
            'billing_interval' => (string) $plan['billing_interval'],
            'display_price' => (string) $plan['display_price'],
            'stripe_price_id' => $stripePriceId === null ? null : (string) $stripePriceId,
        ];
    }
}
