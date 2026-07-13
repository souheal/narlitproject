<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSubscriptionsRevenueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        Config::set('services.stripe.fake_checkout', true);
        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_subscription_summary(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $monthlyUser = $this->userWithRole('subscriber', 'monthly@test.com');
        $yearlyUser = $this->userWithRole('subscriber', 'yearly@test.com');
        $canceledUser = $this->userWithRole('subscriber', 'canceled@test.com');

        $monthly = $this->subscription($monthlyUser, 'monthly', '7.00', 'active', [
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->addMonth(),
        ]);
        $yearly = $this->subscription($yearlyUser, 'yearly', '96.00', 'active', [
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->addMonths(10),
        ]);
        $this->subscription($canceledUser, 'monthly', '7.00', 'canceled', [
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->addMonth(),
            'canceled_at' => now()->subDays(5),
        ]);

        $this->payment($monthly, '7.00', '0.30', '6.70', now()->subDays(10));
        $this->payment($yearly, '96.00', '3.00', '93.00', now()->subDays(9));

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/subscriptions/summary?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription revenue summary retrieved successfully.')
            ->assertJsonPath('data.kpis.mrr', '15.00')
            ->assertJsonPath('data.kpis.arr', '180.00')
            ->assertJsonPath('data.kpis.arpu', '7.50')
            ->assertJsonPath('data.kpis.active_subscriptions', 2)
            ->assertJsonPath('data.kpis.canceled_subscriptions', 1)
            ->assertJsonPath('data.kpis.churn_rate', 33.33)
            ->assertJsonPath('data.kpis.estimated_ltv', '22.50')
            ->assertJsonPath('data.plan_breakdown.monthly_plan_count', 1)
            ->assertJsonPath('data.plan_breakdown.yearly_plan_count', 1)
            ->assertJsonPath('data.revenue_chart.current.0.month', '2026-07')
            ->assertJsonPath('data.revenue_chart.current.0.gross_revenue', '103.00')
            ->assertJsonPath('data.revenue_chart.current.0.net_revenue', '99.70');
    }

    public function test_admin_can_list_and_view_subscription_details(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $subscriber = $this->userWithRole('subscriber', 'member@test.com', 'Member Person');
        $subscription = $this->subscription($subscriber, 'monthly', '7.00', 'active');
        $payment = $this->payment($subscription, '7.00', '0.30', '6.70', now()->subDay());

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'subscription.canceled',
            'entity_type' => 'subscription',
            'entity_id' => $subscription->public_id,
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/subscriptions?search=member@test.com&status=active&plan=monthly&sort=subscriber&direction=asc&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.data.0.public_id', $subscription->public_id)
            ->assertJsonPath('data.subscriptions.data.0.subscriber.email', 'member@test.com')
            ->assertJsonPath('data.subscriptions.data.0.amount', '7.00')
            ->assertJsonPath('data.subscriptions.data.0.stripe_customer_id', $subscription->stripe_customer_id)
            ->assertJsonPath('data.subscriptions.meta.per_page', 5)
            ->assertJsonMissingPath('data.subscriptions.data.0.stripe_secret');

        $this->getJson("/api/v1/admin/subscriptions/{$subscription->public_id}")
            ->assertOk()
            ->assertJsonPath('data.subscription.subscription.public_id', $subscription->public_id)
            ->assertJsonPath('data.subscription.payments.0.public_id', $payment->public_id)
            ->assertJsonPath('data.subscription.admin_action_history.0.action', 'subscription.canceled');
    }

    public function test_admin_can_cancel_subscription_after_stripe_success(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $subscriber = $this->userWithRole('subscriber', 'member@test.com');
        $subscription = $this->subscription($subscriber, 'monthly', '7.00', 'active');

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/subscriptions/{$subscription->public_id}/cancel", [
            'reason' => 'Customer requested cancellation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'canceled');

        $this->assertSame('canceled', $subscription->refresh()->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertFalse($subscriber->refresh()->is_active);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'subscription.canceled',
            'entity_type' => 'subscription',
            'entity_id' => $subscription->public_id,
        ]);
    }

    public function test_admin_can_refund_paid_payment_idempotently(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $subscriber = $this->userWithRole('subscriber', 'member@test.com');
        $subscription = $this->subscription($subscriber, 'monthly', '7.00', 'active');
        $payment = $this->payment($subscription, '7.00', '0.30', '6.70', now()->subDay());

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded')
            ->assertJsonPath('data.payment.refunded_at', now()->toIso8601String());

        $this->assertSame('refunded', $payment->refresh()->status);
        $this->assertNotNull($payment->metadata['admin_refund']['stripe_refund_id'] ?? null);

        $this->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame(1, DB::table('admin_logs')->where('action', 'payment.refunded')->where('entity_id', $payment->public_id)->count());
    }

    public function test_refund_requires_reason_and_paid_payment(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $subscriber = $this->userWithRole('subscriber', 'member@test.com');
        $subscription = $this->subscription($subscriber, 'monthly', '7.00', 'active');
        $payment = $this->payment($subscription, '7.00', '0.30', '6.70', now()->subDay(), [
            'status' => 'failed',
            'stripe_payment_intent' => 'pi_failed',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payments/{$payment->public_id}/refund")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only paid payments can be refunded.');
    }

    public function test_subscription_revenue_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/subscriptions')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com'));

        $this->getJson('/api/v1/admin/subscriptions')->assertForbidden();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('personal_access_tokens');
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
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
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

        Schema::create('admin_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function userWithRole(string $role, string $email, ?string $name = null): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => $name ?? str($role)->headline().' User',
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => now(),
            'failed_login_attempts' => 0,
        ]);
    }

    private function subscription(User $user, string $plan, string $amount, string $status, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_'.str()->random(10),
            'stripe_subscription_id' => 'sub_'.str()->random(10),
            'plan' => $plan,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => $status,
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ], $overrides));
    }

    private function payment(Subscription $subscription, string $amount, string $fee, string $net, Carbon $paidAt, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_'.str()->random(10),
            'stripe_invoice_id' => 'in_'.str()->random(10),
            'amount' => $amount,
            'stripe_fee' => $fee,
            'net_amount' => $net,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => $paidAt,
        ], $overrides));
    }
}
