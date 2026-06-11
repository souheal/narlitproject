<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected SubscriptionService $subscriptionService,
    ) {
    }

    public function createForVerifiedUser(User $user, ?string $requestedPlan = null): array
    {
        if ($user->email_verified_at === null) {
            throw new ApiException('OTP verification is required before starting checkout.', 403);
        }

        if ($this->subscriptionService->userHasActiveSubscription($user)) {
            throw new ApiException('An active subscription already exists for this account.', 409);
        }

        $plan = $requestedPlan ?? cache()->get($this->registrationService->planCacheKey($user->id), 'monthly');

        if (! in_array($plan, ['monthly', 'yearly'], true)) {
            throw new ApiException('A valid pending subscription plan was not found for this user.', 422);
        }

        if ((bool) config('services.stripe.fake_checkout', false)) {
            return $this->completeFakeCheckout($user, $plan);
        }

        $customerId = Subscription::query()
            ->where('user_id', $user->id)
            ->value('stripe_customer_id');

        if ($customerId === null) {
            $customerId = cache()->rememberForever("narlit:stripe-customer:{$user->id}", function () use ($user) {
                $customer = $this->client()->customers->create([
                    'email' => $user->email,
                    'name' => $user->full_name,
                    'phone' => $user->phone,
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'user_public_id' => $user->public_id,
                    ],
                ]);

                return $customer->id;
            });
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $user->id,
            'success_url' => config('services.stripe.success_url').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('services.stripe.cancel_url'),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower((string) config('services.stripe.currency', 'USD')),
                    'unit_amount' => $this->amountForPlan($plan),
                    'product_data' => [
                        'name' => $plan === 'monthly' ? 'NarLit Monthly Subscription' : 'NarLit Yearly Subscription',
                    ],
                    'recurring' => [
                        'interval' => $plan === 'monthly' ? 'month' : 'year',
                    ],
                ],
            ]],
            'metadata' => [
                'user_id' => (string) $user->id,
                'user_public_id' => $user->public_id,
                'plan' => $plan,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'user_public_id' => $user->public_id,
                    'plan' => $plan,
                ],
            ],
        ]);

        return [
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
            'expires_at' => $session->expires_at,
        ];
    }

    protected function amountForPlan(string $plan): int
    {
        return $plan === 'yearly'
            ? (int) config('services.stripe.yearly_amount', 9600)
            : (int) config('services.stripe.monthly_amount', 700);
    }

    protected function completeFakeCheckout(User $user, string $plan): array
    {
        $startedAt = now();
        $expiresAt = $plan === 'yearly'
            ? $startedAt->copy()->addYear()
            : $startedAt->copy()->addMonth();

        $subscription = $this->subscriptionService->syncSubscription($user, [
            'public_id' => (string) Str::uuid(),
            'stripe_customer_id' => 'fake_customer_'.$user->id,
            'stripe_subscription_id' => 'fake_subscription_'.$user->id.'_'.$startedAt->timestamp,
            'plan' => $plan,
            'amount' => number_format($this->amountForPlan($plan) / 100, 2, '.', ''),
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

    protected function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '' || $secret === 'sk_test_replace_me' || $secret === 'sk_live_replace_me') {
            throw new ApiException('Stripe test mode is not configured. Set a real STRIPE_SECRET test key in the environment.', 500);
        }

        return new StripeClient($secret);
    }
}
