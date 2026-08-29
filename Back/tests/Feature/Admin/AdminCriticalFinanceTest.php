<?php

namespace Tests\Feature\Admin;

use App\Http\Resources\Admin\AdminPaymentResource;
use App\Http\Resources\Admin\AdminPayoutItemResource;
use App\Http\Resources\Admin\AdminSubscriptionDetailResource;
use App\Http\Resources\Admin\AdminSubscriptionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\InteractsWithCriticalAdminData;
use Tests\TestCase;

class AdminCriticalFinanceTest extends TestCase
{
    use InteractsWithCriticalAdminData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->prepareCriticalAdminTest();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_finance_admin_refunds_payment_once_with_idempotent_replay(): void
    {
        $admin = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $payment = $this->createRefundablePayment($member);
        $key = (string) str()->uuid();

        Sanctum::actingAs($admin);

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame('refunded', $payment->refresh()->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertNotNull($payment->metadata['admin_refund']['stripe_refund_id'] ?? null);
        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertSame(1, $this->auditCount('payment.refunded', $payment->public_id));

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true')
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame(1, $this->auditCount('payment.refunded', $payment->public_id));
    }

    public function test_refund_idempotency_validation_and_conflicts_are_enforced(): void
    {
        $admin = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $payment = $this->createRefundablePayment($member);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'An idempotency key is required.');

        $this->withHeader('Idempotency-Key', 'not-a-uuid')->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The idempotency key must be a valid UUID.');

        $key = (string) str()->uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertOk();

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Different reason for the same key.',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This idempotency key was already used for a different request.');

        $this->assertDatabaseMissing('idempotency_keys', [
            'request_method' => 'GET',
        ]);
    }

    public function test_refund_business_rules_and_permissions_are_enforced(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $content = $this->createAdminWithRole('admin_content');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $member = $this->createUserWithRole('subscriber');
        $payment = $this->createRefundablePayment($member);
        $failedPayment = $this->createRefundablePayment($member, null, [
            'status' => 'failed',
            'stripe_payment_intent' => 'pi_failed_'.str()->random(8),
        ]);

        Sanctum::actingAs($content);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertForbidden();

        Sanctum::actingAs($readonly);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertForbidden();

        Sanctum::actingAs($member);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertForbidden();

        Sanctum::actingAs($finance);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payments/missing-payment/refund', [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertNotFound();

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$failedPayment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only paid payments can be refunded.');

        $this->assertSame('failed', $failedPayment->refresh()->status);
    }

    public function test_finance_admin_cancels_subscription_once_with_idempotent_replay(): void
    {
        $admin = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $subscription = $this->createActiveSubscription($member);
        $key = (string) str()->uuid();

        Sanctum::actingAs($admin);

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel", [
            'reason' => 'Customer asked support to cancel.',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'canceled');

        $this->assertSame('canceled', $subscription->refresh()->status);
        $this->assertFalse($member->refresh()->is_active);
        $this->assertSame(1, $this->auditCount('subscription.canceled', $subscription->public_id));

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel", [
            'reason' => 'Customer asked support to cancel.',
        ])
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame(1, $this->auditCount('subscription.canceled', $subscription->public_id));
    }

    public function test_subscription_cancel_validation_not_found_and_permissions_are_enforced(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $content = $this->createAdminWithRole('admin_content');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $member = $this->createUserWithRole('subscriber');
        $subscription = $this->createActiveSubscription($member);

        Sanctum::actingAs($finance);
        $this->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', 'An idempotency key is required.');

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/subscriptions/missing-sub/cancel')
            ->assertNotFound();

        Sanctum::actingAs($content);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel")
            ->assertForbidden();

        Sanctum::actingAs($readonly);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel")
            ->assertForbidden();
    }

    public function test_subscription_stripe_identifiers_require_finance_permission(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $settings = $this->createAdminWithRole('admin_settings');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $member = $this->createUserWithRole('subscriber');
        $subscription = $this->createActiveSubscription($member, [
            'stripe_customer_id' => 'cus_sensitive',
            'stripe_subscription_id' => 'sub_sensitive',
        ]);

        Sanctum::actingAs($settings);
        $this->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.data.0.public_id', $subscription->public_id)
            ->assertJsonPath('data.subscriptions.data.0.stripe_customer_id', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_subscription_id', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.customer', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.subscription', null);

        Sanctum::actingAs($readonly);
        $this->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.data.0.public_id', $subscription->public_id)
            ->assertJsonPath('data.subscriptions.data.0.amount', '7.00')
            ->assertJsonPath('data.subscriptions.data.0.status', 'active')
            ->assertJsonPath('data.subscriptions.data.0.stripe_customer_id', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_subscription_id', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.customer', null)
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.subscription', null);

        Sanctum::actingAs($finance);
        $this->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.data.0.public_id', $subscription->public_id)
            ->assertJsonPath('data.subscriptions.data.0.stripe_customer_id', 'cus_sensitive')
            ->assertJsonPath('data.subscriptions.data.0.stripe_subscription_id', 'sub_sensitive')
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.customer', 'https://dashboard.stripe.com/customers/cus_sensitive')
            ->assertJsonPath('data.subscriptions.data.0.stripe_links.subscription', 'https://dashboard.stripe.com/subscriptions/sub_sensitive');
    }

    public function test_sensitive_admin_resources_fail_closed_when_permission_state_is_unavailable(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $subscription = $this->createActiveSubscription($member, [
            'stripe_customer_id' => 'cus_fail_closed',
            'stripe_subscription_id' => 'sub_fail_closed',
        ]);
        $payment = $this->createRefundablePayment($member, $subscription, [
            'stripe_payment_intent' => 'pi_fail_closed',
            'stripe_invoice_id' => 'in_fail_closed',
        ]);
        $organization = $this->createOrganizationProfile([
            'stripe_connect_account_id' => 'acct_fail_closed',
        ]);
        $batch = $this->createPendingPayoutBatch($organization);
        $payoutItem = $batch->items()->firstOrFail();
        $payoutItem->forceFill([
            'stripe_transfer_id' => 'tr_fail_closed',
            'metadata' => [
                'failure_reason' => 'Sensitive provider detail.',
            ],
        ])->save();

        $subscription->setRelation('payments', collect([$payment]));
        $subscription->setAttribute('admin_action_history', collect());

        $request = Request::create('/api/v1/admin/test');
        $request->setUserResolver(fn () => $finance);

        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('permissions')
            ->andReturn(false);

        $paymentData = (new AdminPaymentResource($payment))->toArray($request);
        $subscriptionData = (new AdminSubscriptionResource($subscription))->toArray($request);
        $subscriptionDetailData = (new AdminSubscriptionDetailResource($subscription))->toArray($request);
        $payoutData = (new AdminPayoutItemResource($payoutItem))->toArray($request);

        $this->assertNull($paymentData['stripe_payment_intent']);
        $this->assertNull($paymentData['stripe_invoice_id']);
        $this->assertNull($subscriptionData['stripe_customer_id']);
        $this->assertNull($subscriptionData['stripe_subscription_id']);
        $this->assertNull($subscriptionData['stripe_links']['customer']);
        $this->assertNull($subscriptionDetailData['payments'][0]['stripe_payment_intent']);
        $this->assertNull($subscriptionDetailData['payments'][0]['stripe_links']['invoice']);
        $this->assertNull($payoutData['organization']['stripe_connect_account_id']);
        $this->assertNull($payoutData['stripe_transfer_id']);
        $this->assertNull($payoutData['failure_reason']);
        $this->assertNull($payoutData['metadata']);
    }

    protected function auditCount(string $action, string $entityId): int
    {
        return (int) DB::table('admin_logs')
            ->where('action', $action)
            ->where('entity_id', $entityId)
            ->count();
    }
}
