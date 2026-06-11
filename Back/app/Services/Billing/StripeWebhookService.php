<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {
    }

    public function handle(string $payload, string $signature): void
    {
        $event = $this->constructEvent($payload, $signature);
        $processedKey = "narlit:stripe:webhook:processed:{$event->id}";
        $lock = Cache::lock("narlit:stripe:webhook:lock:{$event->id}", 30);

        $lock->block(5, function () use ($event, $processedKey): void {
            if (Cache::has($processedKey)) {
                return;
            }

            DB::transaction(function () use ($event): void {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutCompleted($event),
                    'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $this->handleSubscriptionEvent($event),
                    'invoice.payment_succeeded' => $this->handleInvoicePaid($event),
                    'invoice.payment_failed' => $this->handleInvoiceFailed($event),
                    default => null,
                };
            }, 3);

            Cache::put($processedKey, true, now()->addDays(7));
        });
    }

    protected function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;

        if (($session->mode ?? null) !== 'subscription' || empty($session->subscription)) {
            return;
        }

        $user = $this->resolveUserFromMetadata((array) ($session->metadata ?? []), $session->client_reference_id ?? null);
        $stripeSubscription = $this->client()->subscriptions->retrieve((string) $session->subscription, []);
        $subscription = $this->syncStripeSubscription($user, $stripeSubscription, (string) ($session->metadata->plan ?? 'monthly'));

        if (
            $subscription->status === 'active'
            && $subscription->expires_at?->isFuture()
            && $user->email_verified_at !== null
            && (($session->payment_status ?? null) === 'paid' || ($stripeSubscription->status ?? null) === 'active')
        ) {
            $this->subscriptionService->activateUser($user);
        }
    }

    protected function handleSubscriptionEvent(Event $event): void
    {
        $stripeSubscription = $event->data->object;
        $user = $this->resolveUserFromStripeSubscription($stripeSubscription);
        $plan = $this->resolvePlanFromStripe($stripeSubscription, $stripeSubscription->metadata->plan ?? null);

        $this->syncStripeSubscription($user, $stripeSubscription, $plan);
    }

    protected function handleInvoicePaid(Event $event): void
    {
        $invoice = $event->data->object;

        if (empty($invoice->subscription)) {
            return;
        }

        $stripeSubscription = $this->client()->subscriptions->retrieve((string) $invoice->subscription, []);
        $user = $this->resolveUserFromStripeSubscription($stripeSubscription);
        $subscription = $this->syncStripeSubscription($user, $stripeSubscription, $this->resolvePlanFromStripe($stripeSubscription, $stripeSubscription->metadata->plan ?? null));

        $paymentIntent = is_string($invoice->payment_intent) ? $invoice->payment_intent : 'invoice_'.$invoice->id;
        $amount = ((int) ($invoice->amount_paid ?? 0)) / 100;

        $payment = Payment::query()->firstOrNew([
            'stripe_payment_intent' => $paymentIntent,
        ]);

        if (! $payment->exists) {
            $payment->public_id = (string) Str::uuid();
        }

        $payment->fill([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $invoice->id,
            'amount' => number_format($amount, 2, '.', ''),
            'stripe_fee' => '0.00',
            'net_amount' => number_format($amount, 2, '.', ''),
            'currency' => strtoupper((string) ($invoice->currency ?? 'usd')),
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => [
                'stripe_event_id' => $event->id,
            ],
        ]);
        $payment->save();

        $this->subscriptionService->activateUser($user);
    }

    protected function handleInvoiceFailed(Event $event): void
    {
        $invoice = $event->data->object;

        if (empty($invoice->subscription)) {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', (string) $invoice->subscription)
            ->first();

        if ($subscription === null) {
            return;
        }

        $subscription->forceFill([
            'status' => 'past_due',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_failed_invoice_id' => $invoice->id,
                'last_failed_event_id' => $event->id,
            ]),
        ])->save();

        $this->subscriptionService->deactivateUser($subscription->user);
    }

    protected function syncStripeSubscription(User $user, object $stripeSubscription, ?string $plan): Subscription
    {
        $localStatus = $this->mapStripeStatus((string) $stripeSubscription->status);

        return $this->subscriptionService->syncSubscription($user, [
            'public_id' => (string) Str::uuid(),
            'stripe_customer_id' => (string) $stripeSubscription->customer,
            'stripe_subscription_id' => (string) $stripeSubscription->id,
            'plan' => $plan ?? 'monthly',
            'amount' => number_format(((int) ($stripeSubscription->items->data[0]->price->unit_amount ?? 0)) / 100, 2, '.', ''),
            'currency' => strtoupper((string) ($stripeSubscription->currency ?? 'usd')),
            'status' => $localStatus,
            'started_at' => now()->createFromTimestampUTC((int) $stripeSubscription->current_period_start),
            'expires_at' => now()->createFromTimestampUTC((int) $stripeSubscription->current_period_end),
            'canceled_at' => isset($stripeSubscription->canceled_at) && $stripeSubscription->canceled_at ? now()->createFromTimestampUTC((int) $stripeSubscription->canceled_at) : null,
            'trial_ends_at' => isset($stripeSubscription->trial_end) && $stripeSubscription->trial_end ? now()->createFromTimestampUTC((int) $stripeSubscription->trial_end) : null,
            'metadata' => [
                'stripe_status' => $stripeSubscription->status,
            ],
        ]);
    }

    protected function resolveUserFromMetadata(array $metadata, mixed $clientReferenceId = null): User
    {
        $userId = $metadata['user_id'] ?? $clientReferenceId;
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new ApiException('Stripe webhook user mapping failed.', 404);
        }

        return $user;
    }

    protected function resolveUserFromStripeSubscription(object $stripeSubscription): User
    {
        $userId = $stripeSubscription->metadata->user_id ?? null;

        if ($userId !== null) {
            $user = User::query()->find($userId);

            if ($user !== null) {
                return $user;
            }
        }

        $existingSubscription = Subscription::query()
            ->where('stripe_subscription_id', (string) $stripeSubscription->id)
            ->first();

        if ($existingSubscription !== null) {
            return $existingSubscription->user;
        }

        throw new ApiException('Stripe webhook user mapping failed.', 404);
    }

    protected function resolvePlanFromStripe(object $stripeSubscription, ?string $fallback): string
    {
        $interval = $stripeSubscription->items->data[0]->price->recurring->interval ?? null;

        return match ($interval) {
            'year' => 'yearly',
            'month' => 'monthly',
            default => $fallback ?? 'monthly',
        };
    }

    protected function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'active', 'trialing' => 'active',
            'canceled', 'incomplete_expired' => 'canceled',
            'past_due' => 'past_due',
            'unpaid' => 'unpaid',
            default => 'incomplete',
        };
    }

    protected function constructEvent(string $payload, string $signature): Event
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new ApiException('Stripe webhook secret is not configured.', 500);
        }

        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            throw new ApiException('Invalid Stripe webhook signature.', 400);
        }
    }

    protected function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new ApiException('Stripe is not configured.', 500);
        }

        return new StripeClient($secret);
    }
}
