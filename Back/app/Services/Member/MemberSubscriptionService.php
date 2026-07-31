<?php

namespace App\Services\Member;

<<<<<<< HEAD
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;

class MemberSubscriptionService
{
    public function show(User $user): array
    {
        $subscription = $this->latest($user);
        $currency = $subscription?->currency ?? (string) config('services.stripe.currency', 'USD');
        $currentPlan = $subscription?->plan;
        $plans = $this->planCatalogue($currentPlan, $currency);

        return [
            'subscription' => $this->present($subscription),
            'plans' => $plans,
            'invoices' => $this->invoices($user),
        ];
    }

    public function changePlan(User $user, string $plan): array
    {
        if (! in_array($plan, ['monthly', 'yearly'], true)) {
            throw new ApiException('Choose a valid plan.', 422);
        }

        $subscription = $this->latest($user);
        if ($subscription === null) {
            throw new ApiException('You do not have an active subscription to change.', 404);
        }

        $catalog = $this->planCatalogue($subscription->plan, $subscription->currency);
        $target = collect($catalog)->firstWhere('key', $plan);

        $subscription->plan = $plan;
        $subscription->amount = (float) ($target['amount'] ?? $subscription->amount);
        $subscription->status = 'active';
        $subscription->canceled_at = null;
        $subscription->save();

        return array_merge(
            ['subscription' => $this->present($subscription->refresh())],
            ['plans' => $this->planCatalogue($subscription->plan, $subscription->currency)],
        );
    }

    public function cancel(User $user): array
    {
        $subscription = $this->latest($user);
        if ($subscription === null) {
            throw new ApiException('No subscription found to cancel.', 404);
        }

        if ($subscription->canceled_at === null) {
            $subscription->canceled_at = now();
            $subscription->save();
        }

        return ['subscription' => $this->present($subscription->refresh())];
    }

    public function resume(User $user): array
    {
        $subscription = $this->latest($user);
        if ($subscription === null) {
            throw new ApiException('No subscription found to resume.', 404);
        }

        $expiresAt = $subscription->expires_at;
        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new ApiException('Subscription has fully expired and cannot be resumed.', 422);
        }

        $subscription->canceled_at = null;
        $subscription->status = 'active';
        $subscription->save();

        return ['subscription' => $this->present($subscription->refresh())];
    }

    public function updatePaymentMethod(User $user): array
    {
        $subscription = $this->latest($user);
        if ($subscription === null) {
            throw new ApiException('No subscription found.', 404);
        }

        $portalUrl = (string) config('services.stripe.customer_portal_url', 'https://billing.stripe.com/p/session');

        return [
            'checkout_url' => $portalUrl,
            'portal_url' => $portalUrl,
        ];
    }

    protected function latest(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->first();
    }

    protected function present(?Subscription $subscription): array
    {
        if ($subscription === null) {
            return [
                'public_id' => null,
                'plan' => null,
                'amount' => '0.00',
                'currency' => (string) config('services.stripe.currency', 'USD'),
                'status' => 'inactive',
                'started_at' => null,
                'expires_at' => null,
                'canceled_at' => null,
                'next_billing_date' => null,
                'current_period_start' => null,
                'current_period_end' => null,
                'cancel_at' => null,
                'card_brand' => null,
                'card_last_four' => null,
                'payment_method_id' => null,
                'payment_method' => null,
            ];
        }

        $meta = $subscription->metadata ?? [];
        $cardBrand = $meta['card_brand'] ?? null;
        $cardLast4 = $meta['card_last4'] ?? ($meta['card_last_four'] ?? null);
        $expMonth = $meta['card_exp_month'] ?? null;
        $expYear = $meta['card_exp_year'] ?? null;
        $paymentMethodId = $meta['payment_method_id'] ?? null;

        return [
            'public_id' => $subscription->public_id,
            'plan' => $subscription->plan,
            'amount' => number_format((float) $subscription->amount, 2, '.', ''),
            'currency' => $subscription->currency,
            'status' => $subscription->status,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            'cancel_at' => $subscription->canceled_at?->toIso8601String(),
            'current_period_start' => $subscription->started_at?->toIso8601String(),
            'current_period_end' => $subscription->expires_at?->toIso8601String(),
            'next_billing_date' => $subscription->canceled_at === null
                ? $subscription->expires_at?->toIso8601String()
                : null,
            'card_brand' => $cardBrand,
            'card_last_four' => $cardLast4,
            'payment_method_id' => $paymentMethodId,
            'payment_method' => $cardLast4 ? [
                'brand' => $cardBrand,
                'last4' => $cardLast4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
            ] : null,
        ];
    }

    protected function planCatalogue(?string $currentPlan, string $currency): array
    {
        $monthlyAmount = (float) config('services.stripe.monthly_price', 12.00);
        $yearlyAmount = (float) config('services.stripe.yearly_price', 120.00);

        return [
            [
                'key' => 'monthly',
                'name' => 'Monthly',
                'amount' => number_format($monthlyAmount, 2, '.', ''),
                'price' => number_format($monthlyAmount, 2, '.', ''),
                'interval' => 'month',
                'currency' => $currency,
                'features' => [
                    'Unlimited story access',
                    'Direct funding of the nonprofits you read',
                    'Cancel anytime',
                ],
                'is_current' => $currentPlan === 'monthly',
            ],
            [
                'key' => 'yearly',
                'name' => 'Yearly',
                'amount' => number_format($yearlyAmount, 2, '.', ''),
                'price' => number_format($yearlyAmount, 2, '.', ''),
                'interval' => 'year',
                'currency' => $currency,
                'features' => [
                    'Everything in Monthly',
                    'Two months free vs. monthly billing',
                    'Priority support',
                ],
                'is_current' => $currentPlan === 'yearly',
                'savings_note' => 'Save 17% vs monthly',
            ],
        ];
    }

    protected function invoices(User $user): array
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->orderByDesc('paid_at')
            ->limit(24)
            ->get()
            ->map(fn (Payment $p): array => [
                'public_id' => $p->public_id,
                'amount' => number_format((float) $p->amount, 2, '.', ''),
                'currency' => $p->currency,
                'status' => $p->status,
                'invoice_number' => $p->stripe_invoice_id,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'pdf_url' => null,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }
=======
use App\Services\Admin\PlatformSettingsService;

class MemberSubscriptionService
{
    public function __construct(
        protected PlatformSettingsService $settings,
    ) {}

    public function planCatalogue(): array
    {
        $settings = $this->settings->group('subscription_plans');
        $plans = $settings['plans'] ?? [];

        if (! is_array($plans)) {
            return [];
        }

        return collect($plans)
            ->filter(fn (mixed $plan): bool => is_array($plan) && ($plan['enabled'] ?? false) === true)
            ->map(fn (array $plan): array => $this->normalisePlan($plan))
            ->filter(fn (array $plan): bool => $plan !== [])
            ->values()
            ->all();
    }

    protected function normalisePlan(array $plan): array
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
            'enabled' => true,
        ];
    }
>>>>>>> 2ebe816874cc743bde1c98d7c1016fe19fc26961
}
