<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\PlatformSettingsService;
use App\Services\Auth\RegistrationService;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected SubscriptionService $subscriptionService,
        protected PlatformSettingsService $settings,
    ) {}

    public function createForVerifiedUser(User $user, ?string $requestedPlan = null): array
    {
        if ($user->email_verified_at === null) {
            throw new ApiException('OTP verification is required before starting checkout.', 403);
        }

        if ($this->subscriptionService->userHasActiveSubscription($user)) {
            throw new ApiException('An active subscription already exists for this account.', 409);
        }

        $planKey = (string) ($requestedPlan ?? cache()->get($this->registrationService->planCacheKey($user->id), 'monthly'));
        $plan = $this->resolvePlan($planKey);

        if ((bool) config('services.stripe.fake_checkout', false)) {
            return $this->completeFakeCheckout($user, $plan);
        }

        $customerId = Subscription::query()
            ->where('user_id', $user->id)
            ->value('stripe_customer_id');

        if ($customerId === null) {
            $customerId = cache()->rememberForever("narlit:stripe-customer:{$user->id}", function () use ($user) {
                return $this->createStripeCustomer($user);
            });
        }

        try {
            $session = $this->createStripeCheckoutSession([
                'mode' => 'subscription',
                'customer' => $customerId,
                'client_reference_id' => (string) $user->id,
                'success_url' => config('services.stripe.success_url').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('services.stripe.cancel_url'),
                'line_items' => [[
                    'price' => $plan['stripe_price_id'],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'user_public_id' => $user->public_id,
                    'selected_plan_key' => $plan['key'],
                    'plan' => $plan['key'],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'user_public_id' => $user->public_id,
                        'selected_plan_key' => $plan['key'],
                        'plan' => $plan['key'],
                    ],
                ],
            ], $this->checkoutIdempotencyKey($user, $plan['key']));
        } catch (ApiErrorException $exception) {
            throw new ApiException('Unable to create the checkout session. Please try again.', 502);
        }

        return [
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
            'expires_at' => $session->expires_at,
        ];
    }

    protected function resolvePlan(string $planKey): array
    {
        $plans = $this->settings->group('subscription_plans')['plans'] ?? [];

        if (! is_array($plans)) {
            throw new ApiException('The selected subscription plan is unavailable.', 422);
        }

        foreach ($plans as $plan) {
            if (! is_array($plan) || ($plan['key'] ?? null) !== $planKey) {
                continue;
            }

            if (($plan['enabled'] ?? false) !== true) {
                throw new ApiException('The selected subscription plan is currently disabled.', 422);
            }

            if (! $this->hasValidStripePriceId($plan)) {
                throw new ApiException('The selected subscription plan is not configured for payment.', 422);
            }

            return [
                'key' => (string) $plan['key'],
                'name' => is_scalar($plan['name'] ?? null) ? (string) $plan['name'] : (string) $plan['key'],
                'billing_interval' => is_scalar($plan['billing_interval'] ?? null) ? (string) $plan['billing_interval'] : '',
                'display_price' => is_scalar($plan['display_price'] ?? null) ? (string) $plan['display_price'] : '',
                'stripe_price_id' => (string) $plan['stripe_price_id'],
                'enabled' => true,
            ];
        }

        throw new ApiException('The selected subscription plan is unavailable.', 422);
    }

    protected function hasValidStripePriceId(array $plan): bool
    {
        $priceId = $plan['stripe_price_id'] ?? null;

        return is_string($priceId) && preg_match('/^price_[A-Za-z0-9_]+$/', $priceId) === 1;
    }

    protected function completeFakeCheckout(User $user, array $plan): array
    {
        $startedAt = now();
        $expiresAt = $plan['billing_interval'] === 'yearly'
            ? $startedAt->copy()->addYear()
            : $startedAt->copy()->addMonth();

        $subscription = $this->subscriptionService->syncSubscription($user, [
            'public_id' => (string) Str::uuid(),
            'stripe_customer_id' => 'fake_customer_'.$user->id,
            'stripe_subscription_id' => 'fake_subscription_'.$user->id.'_'.$startedAt->timestamp,
            'plan' => $plan['key'],
            'amount' => $plan['display_price'],
            'currency' => strtoupper((string) config('services.stripe.currency', 'USD')),
            'status' => 'active',
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'canceled_at' => null,
            'trial_ends_at' => null,
            'metadata' => [
                'checkout_mode' => 'fake',
            ],
        ]);

        cache()->forget($this->registrationService->planCacheKey($user->id));

        return [
            'mode' => 'fake',
            'subscription_public_id' => $subscription->public_id,
            'subscription_status' => $subscription->status,
            'plan' => $subscription->plan,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'is_active' => true,
            'next_step' => 'completed',
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ];
    }

    protected function createStripeCustomer(User $user): string
    {
        try {
            $customer = $this->client()->customers->create([
                'email' => $user->email,
                'name' => $user->full_name,
                'phone' => $user->phone,
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'user_public_id' => $user->public_id,
                ],
            ]);
        } catch (ApiErrorException $exception) {
            throw new ApiException('Unable to create the checkout session. Please try again.', 502);
        }

        return $customer->id;
    }

    protected function createStripeCheckoutSession(array $payload, string $idempotencyKey): object
    {
        return $this->client()->checkout->sessions->create($payload, [
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    protected function checkoutIdempotencyKey(User $user, string $planKey): string
    {
        return 'checkout:'.$user->public_id.':'.$planKey.':'.now()->format('YmdHi');
    }

    protected function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '' || $secret === 'sk_test_replace_me' || $secret === 'sk_live_replace_me') {
            throw new ApiException('Stripe test mode is not configured. Set a real STRIPE_SECRET test key in the environment.', 500);
        }

        return new StripeClient($secret);
    }
}
