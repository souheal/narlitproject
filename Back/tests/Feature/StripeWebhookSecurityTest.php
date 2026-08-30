<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\OrganizationProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\StripeWebhookService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Stripe\Event;
use Tests\TestCase;

class StripeWebhookSecurityTest extends TestCase
{
    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        config()->set('services.stripe.webhook_secret', $this->webhookSecret);
        config()->set('services.stripe.secret', 'sk_test_fake');
    }

    public function test_valid_signature_unsupported_event_is_ignored_without_authentication(): void
    {
        $fake = $this->fakeWebhookService();
        $this->app->instance(StripeWebhookService::class, $fake);

        $payload = $this->eventPayload('evt_unsupported', 'customer.created', [
            'id' => 'cus_test_123',
            'object' => 'customer',
        ]);

        $this->rawWebhookPost($payload)
            ->assertOk()
            ->assertJsonPath('message', 'Webhook event ignored.');

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_unsupported',
            'event_type' => 'customer.created',
            'status' => 'processed',
        ]);
    }

    public function test_missing_invalid_signature_and_invalid_json_return_safe_bad_request(): void
    {
        $payload = $this->eventPayload('evt_bad_sig', 'customer.created', [
            'id' => 'cus_test_123',
            'object' => 'customer',
        ]);

        $this->rawWebhookPost($payload, '')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid webhook request.');

        $this->rawWebhookPost($payload, 't='.time().',v1=invalid')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid webhook request.');

        $invalidJson = '{"id":';

        $this->rawWebhookPost($invalidJson)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid webhook request.')
            ->assertSee('Invalid webhook request.')
            ->assertDontSee('Invalid payload')
            ->assertDontSee($invalidJson);
    }

    public function test_missing_webhook_secret_does_not_bypass_verification(): void
    {
        config()->set('services.stripe.webhook_secret', null);

        $payload = $this->eventPayload('evt_missing_secret', 'customer.created', [
            'id' => 'cus_test_123',
            'object' => 'customer',
        ]);

        $this->rawWebhookPost($payload)
            ->assertStatus(503)
            ->assertJsonPath('message', 'Webhook processing is temporarily unavailable.');

        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_verified_checkout_invoice_and_subscription_deleted_events_are_processed(): void
    {
        $fake = $this->fakeWebhookService();
        $this->app->instance(StripeWebhookService::class, $fake);
        $user = $this->user();

        $checkoutPayload = $this->eventPayload('evt_checkout', 'checkout.session.completed', [
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'subscription' => 'sub_test_123',
            'customer' => 'cus_test_123',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'monthly',
            ],
        ]);

        $this->signedPost($checkoutPayload)->assertOk()->assertJsonPath('message', 'Webhook processed.');

        $subscription = Subscription::query()->where('stripe_subscription_id', 'sub_test_123')->firstOrFail();
        $this->assertTrue($user->refresh()->is_active);
        $this->assertSame('active', $subscription->status);

        $invoicePayload = $this->eventPayload('evt_invoice_paid', 'invoice.payment_succeeded', [
            'id' => 'in_test_123',
            'object' => 'invoice',
            'subscription' => 'sub_test_123',
            'payment_intent' => 'pi_test_123',
            'amount_paid' => 700,
            'currency' => 'usd',
        ]);

        $this->signedPost($invoicePayload)->assertOk();

        $this->assertDatabaseHas('payments', [
            'stripe_payment_intent' => 'pi_test_123',
            'stripe_invoice_id' => 'in_test_123',
            'status' => 'paid',
            'amount' => '7',
        ]);

        $deletedPayload = $this->eventPayload('evt_subscription_deleted', 'customer.subscription.deleted', [
            'id' => 'sub_test_123',
            'object' => 'subscription',
            'customer' => 'cus_test_123',
            'status' => 'canceled',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'monthly',
            ],
        ]);

        $this->signedPost($deletedPayload)->assertOk();

        $this->assertSame('canceled', $subscription->refresh()->status);
    }

    public function test_duplicate_processed_event_returns_ok_and_does_not_rerun_business_logic(): void
    {
        $fake = $this->fakeWebhookService();
        $this->app->instance(StripeWebhookService::class, $fake);
        $user = $this->user();
        $payload = $this->eventPayload('evt_duplicate_invoice', 'invoice.payment_succeeded', [
            'id' => 'in_duplicate',
            'object' => 'invoice',
            'subscription' => 'sub_duplicate',
            'payment_intent' => 'pi_duplicate',
            'amount_paid' => 700,
            'currency' => 'usd',
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');
        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook event already processed.');

        $this->assertSame(1, $fake->handledCount('evt_duplicate_invoice'));
        $this->assertSame(1, Payment::query()->where('stripe_payment_intent', 'pi_duplicate')->count());
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_duplicate_invoice',
            'status' => 'processed',
            'attempts' => 1,
        ]);
    }

    public function test_internal_processing_failure_is_recorded_and_can_be_retried(): void
    {
        $failing = $this->fakeWebhookService(failEventId: 'evt_retry');
        $this->app->instance(StripeWebhookService::class, $failing);
        $user = $this->user();
        $payload = $this->eventPayload('evt_retry', 'checkout.session.completed', [
            'id' => 'cs_retry',
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'subscription' => 'sub_retry',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'monthly',
            ],
        ]);

        $this->signedPost($payload)
            ->assertStatus(500)
            ->assertJsonPath('message', 'Webhook processing failed.')
            ->assertDontSee('Simulated Stripe webhook failure');

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_retry',
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $failing->disableFailure();

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_retry',
            'status' => 'processed',
            'attempts' => 2,
        ]);
        $this->assertSame(1, Subscription::query()->where('stripe_subscription_id', 'sub_retry')->count());
    }

    public function test_subscription_lifecycle_events_update_local_state_without_duplicate_records(): void
    {
        $user = $this->user();

        $createdPayload = $this->eventPayload('evt_subscription_created', 'customer.subscription.created', $this->subscriptionObject([
            'id' => 'sub_lifecycle',
            'customer' => 'cus_lifecycle',
            'status' => 'trialing',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'yearly',
            ],
            'items' => [
                'data' => [[
                    'price' => [
                        'unit_amount' => 12000,
                        'recurring' => [
                            'interval' => 'year',
                        ],
                    ],
                ]],
            ],
        ]));

        $this->signedPost($createdPayload)->assertOk()->assertJsonPath('message', 'Webhook processed.');

        $subscription = Subscription::query()->where('stripe_subscription_id', 'sub_lifecycle')->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('yearly', $subscription->plan);
        $this->assertTrue((bool) $user->refresh()->is_active);

        $updatedPayload = $this->eventPayload('evt_subscription_updated', 'customer.subscription.updated', $this->subscriptionObject([
            'id' => 'sub_lifecycle',
            'customer' => 'cus_lifecycle',
            'status' => 'past_due',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'yearly',
            ],
            'items' => [
                'data' => [[
                    'price' => [
                        'unit_amount' => 12000,
                        'recurring' => [
                            'interval' => 'year',
                        ],
                    ],
                ]],
            ],
        ]));

        $this->signedPost($updatedPayload)->assertOk();
        $this->assertSame('past_due', $subscription->refresh()->status);
        $this->assertFalse((bool) $user->refresh()->is_active);

        $deletedPayload = $this->eventPayload('evt_subscription_deleted_actual', 'customer.subscription.deleted', $this->subscriptionObject([
            'id' => 'sub_lifecycle',
            'customer' => 'cus_lifecycle',
            'status' => 'canceled',
            'canceled_at' => now()->timestamp,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => 'yearly',
            ],
            'items' => [
                'data' => [[
                    'price' => [
                        'unit_amount' => 12000,
                        'recurring' => [
                            'interval' => 'year',
                        ],
                    ],
                ]],
            ],
        ]));

        $this->signedPost($deletedPayload)->assertOk();
        $this->assertSame('canceled', $subscription->refresh()->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertSame(1, Subscription::query()->where('stripe_subscription_id', 'sub_lifecycle')->count());
    }

    public function test_invoice_payment_succeeded_uses_existing_subscription_and_is_idempotent(): void
    {
        $user = $this->user();
        $subscription = $this->subscription($user, [
            'stripe_subscription_id' => 'sub_invoice_existing',
            'status' => 'active',
        ]);

        $payload = $this->eventPayload('evt_invoice_existing_paid', 'invoice.payment_succeeded', [
            'id' => 'in_existing_paid',
            'object' => 'invoice',
            'subscription' => 'sub_invoice_existing',
            'payment_intent' => 'pi_existing_paid',
            'amount_paid' => 700,
            'currency' => 'usd',
        ]);

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');
        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook event already processed.');

        $this->assertTrue((bool) $user->refresh()->is_active);
        $this->assertSame(1, Payment::query()->where('stripe_payment_intent', 'pi_existing_paid')->count());
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_existing_paid',
            'status' => 'paid',
            'amount' => '7',
        ]);
    }

    public function test_invoice_payment_failed_marks_subscription_past_due_without_granting_access(): void
    {
        $user = $this->user(['is_active' => true]);
        $this->subscription($user, [
            'stripe_subscription_id' => 'sub_invoice_failed',
            'status' => 'active',
        ]);

        $payload = $this->eventPayload('evt_invoice_failed_actual', 'invoice.payment_failed', [
            'id' => 'in_failed_actual',
            'object' => 'invoice',
            'subscription' => 'sub_invoice_failed',
            'currency' => 'usd',
        ]);

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');

        $subscription = Subscription::query()->where('stripe_subscription_id', 'sub_invoice_failed')->firstOrFail();
        $this->assertSame('past_due', $subscription->status);
        $this->assertFalse((bool) $user->refresh()->is_active);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('in_failed_actual', $subscription->metadata['last_failed_invoice_id']);
    }

    public function test_charge_refunded_reconciles_existing_payment_without_duplicate_refund_action(): void
    {
        $user = $this->user();
        $subscription = $this->subscription($user);
        $payment = $this->payment($user, $subscription, [
            'stripe_payment_intent' => 'pi_refunded_webhook',
            'status' => 'paid',
        ]);

        $payload = $this->eventPayload('evt_charge_refunded', 'charge.refunded', [
            'id' => 'ch_refunded',
            'object' => 'charge',
            'payment_intent' => 'pi_refunded_webhook',
            'amount' => 700,
            'amount_captured' => 700,
            'amount_refunded' => 700,
            'refunded' => true,
        ]);

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');
        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook event already processed.');

        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('ch_refunded', $payment->metadata['stripe_refund_reconciliation']['stripe_charge_id']);
        $this->assertSame(1, Payment::query()->where('stripe_payment_intent', 'pi_refunded_webhook')->count());
    }

    public function test_account_updated_reconciles_connect_status_for_existing_organization(): void
    {
        $organization = $this->organization();

        $payload = $this->eventPayload('evt_account_updated', 'account.updated', [
            'id' => 'acct_webhook',
            'object' => 'account',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        $this->signedPost($payload)->assertOk()->assertJsonPath('message', 'Webhook processed.');

        $organization->refresh();
        $this->assertTrue($organization->charges_enabled);
        $this->assertTrue($organization->payouts_enabled);
        $this->assertTrue($organization->metadata['stripe_connect']['details_submitted']);
        $this->assertSame('evt_account_updated', $organization->metadata['stripe_connect']['stripe_event_id']);
    }

    private function signedPost(string $payload)
    {
        return $this->rawWebhookPost($payload);
    }

    private function rawWebhookPost(string $payload, ?string $signature = null)
    {
        return $this->call('POST', '/api/v1/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature ?? $this->signatureFor($payload),
        ], $payload);
    }

    private function signatureFor(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }

    private function eventPayload(string $id, string $type, array $object): string
    {
        return json_encode([
            'id' => $id,
            'object' => 'event',
            'api_version' => '2026-08-03',
            'created' => time(),
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => null,
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function fakeWebhookService(?string $failEventId = null): FakeStripeWebhookService
    {
        return new FakeStripeWebhookService(app(SubscriptionService::class), $failEventId);
    }

    private function user(array $overrides = []): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'subscriber_'.str()->random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create(array_merge([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => 'Stripe Webhook User',
            'username' => 'stripe_webhook_user_'.str()->random(8),
            'email' => 'stripe-webhook-'.str()->random(8).'@test.com',
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => false,
            'failed_login_attempts' => 0,
        ], $overrides));
    }

    private function subscription(User $user, array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_'.str()->random(8),
            'stripe_subscription_id' => 'sub_'.str()->random(8),
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ], $overrides));
    }

    private function payment(User $user, Subscription $subscription, array $overrides = []): Payment
    {
        return Payment::query()->create(array_merge([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_'.str()->random(8),
            'stripe_invoice_id' => 'in_'.str()->random(8),
            'amount' => '7.00',
            'stripe_fee' => '0.00',
            'net_amount' => '7.00',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ], $overrides));
    }

    private function organization(): OrganizationProfile
    {
        $user = $this->user();

        return OrganizationProfile::query()->create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => 'Webhook Org',
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'organization-certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'stripe_connect_account_id' => 'acct_webhook',
            'payouts_enabled' => false,
            'charges_enabled' => false,
        ]);
    }

    private function subscriptionObject(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'sub_test',
            'object' => 'subscription',
            'customer' => 'cus_test',
            'status' => 'active',
            'currency' => 'usd',
            'current_period_start' => now()->subDay()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'canceled_at' => null,
            'trial_end' => null,
            'metadata' => [
                'plan' => 'monthly',
            ],
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'unit_amount' => 700,
                            'recurring' => [
                                'interval' => 'month',
                            ],
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('role_id')->constrained('roles');
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('organization_name');
            $table->string('tax_id')->unique();
            $table->string('certificate_file');
            $table->boolean('irs_verified')->default(false);
            $table->string('verification_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('stripe_connect_account_id')->nullable()->unique();
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->unique();
            $table->string('plan');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('incomplete');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->string('stripe_payment_intent')->unique();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('stripe_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->string('status', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }
}

class FakeStripeWebhookService extends StripeWebhookService
{
    /** @var array<string, int> */
    private array $handled = [];

    public function __construct(
        SubscriptionService $subscriptionService,
        private ?string $failEventId = null,
    ) {
        parent::__construct($subscriptionService);
    }

    public function handledCount(string $eventId): int
    {
        return $this->handled[$eventId] ?? 0;
    }

    public function disableFailure(): void
    {
        $this->failEventId = null;
    }

    protected function handleCheckoutCompleted(Event $event): bool
    {
        $this->recordHandled($event);
        $session = $event->data->object;
        $user = User::query()->findOrFail((int) $session->metadata->user_id);

        Subscription::query()->firstOrCreate([
            'stripe_subscription_id' => (string) $session->subscription,
        ], [
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => (string) ($session->customer ?? 'cus_'.$user->id),
            'plan' => (string) ($session->metadata->plan ?? 'monthly'),
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'metadata' => ['stripe_event_id' => $event->id],
        ]);

        $user->forceFill(['is_active' => true])->save();

        return true;
    }

    protected function handleInvoicePaid(Event $event): bool
    {
        $this->recordHandled($event);
        $invoice = $event->data->object;
        $subscription = Subscription::query()->firstOrCreate([
            'stripe_subscription_id' => (string) $invoice->subscription,
        ], [
            'public_id' => (string) str()->uuid(),
            'user_id' => User::query()->firstOrFail()->id,
            'stripe_customer_id' => 'cus_invoice_'.str()->random(8),
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Payment::query()->firstOrCreate([
            'stripe_payment_intent' => (string) $invoice->payment_intent,
        ], [
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => (string) $invoice->id,
            'amount' => number_format(((int) $invoice->amount_paid) / 100, 2, '.', ''),
            'stripe_fee' => '0.00',
            'net_amount' => number_format(((int) $invoice->amount_paid) / 100, 2, '.', ''),
            'currency' => strtoupper((string) $invoice->currency),
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => ['stripe_event_id' => $event->id],
        ]);

        return true;
    }

    protected function handleSubscriptionEvent(Event $event): bool
    {
        $this->recordHandled($event);
        $stripeSubscription = $event->data->object;

        Subscription::query()
            ->where('stripe_subscription_id', (string) $stripeSubscription->id)
            ->update([
                'status' => (string) $stripeSubscription->status === 'canceled' ? 'canceled' : 'active',
                'canceled_at' => (string) $stripeSubscription->status === 'canceled' ? now() : null,
            ]);

        return true;
    }

    private function recordHandled(Event $event): void
    {
        if ($event->id === $this->failEventId) {
            throw new RuntimeException('Simulated Stripe webhook failure with private details.');
        }

        $this->handled[$event->id] = ($this->handled[$event->id] ?? 0) + 1;
    }
}
