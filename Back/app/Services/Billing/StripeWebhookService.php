<?php

namespace App\Services\Billing;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Event;
use Stripe\StripeClient;
use Throwable;

class StripeWebhookService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {}

    public function handle(Event $event, ?string $payloadHash = null): string
    {
        try {
            return DB::transaction(function () use ($event, $payloadHash): string {
                $webhookEvent = $this->reserveEvent($event, $payloadHash);

                if ($webhookEvent->status === 'processed') {
                    return 'duplicate';
                }

                DB::table('stripe_webhook_events')
                    ->where('id', $webhookEvent->id)
                    ->update([
                        'status' => 'processing',
                        'attempts' => $webhookEvent->attempts + 1,
                        'updated_at' => now(),
                    ]);

                $handled = match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutCompleted($event),
                    'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $this->handleSubscriptionEvent($event),
                    'invoice.payment_succeeded' => $this->handleInvoicePaid($event),
                    'invoice.payment_failed' => $this->handleInvoiceFailed($event),
                    default => false,
                };

                DB::table('stripe_webhook_events')
                    ->where('id', $webhookEvent->id)
                    ->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'failed_at' => null,
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                return $handled ? 'processed' : 'ignored';
            }, 3);
        } catch (Throwable $exception) {
            $this->recordFailure($event, $payloadHash, $exception);

            throw $exception;
        }
    }

    protected function handleCheckoutCompleted(Event $event): bool
    {
        $session = $event->data->object;

        if (($session->mode ?? null) !== 'subscription' || empty($session->subscription)) {
            return false;
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

        return true;
    }

    protected function handleSubscriptionEvent(Event $event): bool
    {
        $stripeSubscription = $event->data->object;
        $user = $this->resolveUserFromStripeSubscription($stripeSubscription);
        $plan = $this->resolvePlanFromStripe($stripeSubscription, $stripeSubscription->metadata->plan ?? null);

        $this->syncStripeSubscription($user, $stripeSubscription, $plan);

        return true;
    }

    protected function handleInvoicePaid(Event $event): bool
    {
        $invoice = $event->data->object;

        if (empty($invoice->subscription)) {
            return false;
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

        return true;
    }

    protected function handleInvoiceFailed(Event $event): bool
    {
        $invoice = $event->data->object;

        if (empty($invoice->subscription)) {
            return false;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', (string) $invoice->subscription)
            ->first();

        if ($subscription === null) {
            return false;
        }

        $subscription->forceFill([
            'status' => 'past_due',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_failed_invoice_id' => $invoice->id,
                'last_failed_event_id' => $event->id,
            ]),
        ])->save();

        $this->subscriptionService->deactivateUser($subscription->user);

        return true;
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

    protected function reserveEvent(Event $event, ?string $payloadHash): object
    {
        DB::table('stripe_webhook_events')->insertOrIgnore([
            'stripe_event_id' => $event->id,
            'event_type' => $event->type,
            'status' => 'processing',
            'attempts' => 0,
            'payload_hash' => $payloadHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('stripe_webhook_events')
            ->where('stripe_event_id', $event->id)
            ->lockForUpdate()
            ->first();
    }

    protected function recordFailure(Event $event, ?string $payloadHash, Throwable $exception): void
    {
        $safeMessage = str($exception->getMessage())->limit(500)->toString();

        DB::transaction(function () use ($event, $payloadHash, $safeMessage): void {
            DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => $event->id,
                'event_type' => $event->type,
                'status' => 'failed',
                'attempts' => 0,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $webhookEvent = DB::table('stripe_webhook_events')
                ->where('stripe_event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($webhookEvent !== null && $webhookEvent->status !== 'processed') {
                DB::table('stripe_webhook_events')
                    ->where('id', $webhookEvent->id)
                    ->update([
                        'status' => 'failed',
                        'attempts' => $webhookEvent->attempts + 1,
                        'failed_at' => now(),
                        'last_error' => $safeMessage,
                        'updated_at' => now(),
                    ]);
            }
        }, 3);

        Log::error('Stripe webhook processing failed.', [
            'stripe_event_id' => $event->id,
            'event_type' => $event->type,
            'error' => $safeMessage,
        ]);
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
