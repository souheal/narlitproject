<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\Payment;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPayoutManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        Config::set('services.stripe.fake_checkout', true);
        Config::set('services.impact.nonprofit_share_percent', 33);
        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_preview_and_generate_payout_batch_from_net_revenue_and_counted_reads(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $subscriber = $this->userWithRole('subscriber', 'reader@test.com');
        $orgA = $this->organization('Org A', true);
        $orgB = $this->organization('Org B', true);
        $subscription = $this->subscription($subscriber);
        $this->payment($subscription, '100.00', '3.00', '90.00', now()->startOfMonth()->addDays(2));
        $this->readForOrganization($orgA, $subscriber, 2, 20);
        $this->readForOrganization($orgB, $subscriber, 1, 10);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
            'preview' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.calculation.pool_amount', '29.70')
            ->assertJsonPath('data.calculation.distributed_amount', '29.70')
            ->assertJsonPath('data.calculation.inputs.net_collected_subscription_revenue', '90.00')
            ->assertJsonPath('data.calculation.inputs.nonprofit_share_percent', 33)
            ->assertJsonPath('data.calculation.items.0.payout_amount', '19.80')
            ->assertJsonPath('data.calculation.items.1.payout_amount', '9.90');

        $response = $this->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payout_batch.batch.total_pool', '29.70')
            ->assertJsonPath('data.payout_batch.batch.status', 'pending')
            ->assertJsonPath('data.payout_batch.items.0.transfer_status', 'pending');

        $publicId = $response->json('data.payout_batch.batch.public_id');
        $createdBatch = PayoutBatch::where('public_id', $publicId)->firstOrFail();

        $this->assertSame('2026-07-01', $createdBatch->batch_month->toDateString());
        $this->assertSame('29.70', number_format((float) $createdBatch->total_pool, 2, '.', ''));
        $this->assertSame('pending', $createdBatch->status);
        $this->assertDatabaseCount('payout_items', 2);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'payout_batch.generated',
            'entity_type' => 'payout_batch',
            'entity_id' => $publicId,
        ]);
    }

    public function test_duplicate_payout_batch_for_month_is_prevented(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        PayoutBatch::create([
            'public_id' => (string) str()->uuid(),
            'batch_month' => '2026-07-01',
            'total_pool' => '0.00',
            'total_distributed' => '0.00',
            'total_organizations' => 0,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'A payout batch already exists for this month.');
    }

    public function test_admin_can_execute_batch_and_failed_missing_connect_item_can_be_retried(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $ready = $this->organization('Ready Org', true);
        $missing = $this->organization('Missing Org', false);
        $batch = PayoutBatch::create([
            'public_id' => (string) str()->uuid(),
            'batch_month' => '2026-07-01',
            'total_pool' => '30.00',
            'total_distributed' => '30.00',
            'total_organizations' => 2,
            'status' => 'pending',
        ]);
        $readyItem = PayoutItem::create([
            'payout_batch_id' => $batch->id,
            'organization_profile_id' => $ready->id,
            'engagement_score' => '20.0000',
            'payout_amount' => '20.00',
            'total_reads' => 2,
            'total_points' => 20,
            'transfer_status' => 'pending',
        ]);
        $missingItem = PayoutItem::create([
            'payout_batch_id' => $batch->id,
            'organization_profile_id' => $missing->id,
            'engagement_score' => '10.0000',
            'payout_amount' => '10.00',
            'total_reads' => 1,
            'total_points' => 10,
            'transfer_status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.status', 'failed');

        $this->assertSame('completed', $readyItem->refresh()->transfer_status);
        $this->assertSame('fake_transfer_'.$readyItem->id, $readyItem->stripe_transfer_id);
        $this->assertSame('failed', $missingItem->refresh()->transfer_status);
        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertSame('Organization is missing Stripe Connect payout setup.', $missingItem->metadata['failure_reason']);

        $missing->forceFill([
            'stripe_connect_account_id' => 'acct_retry',
            'payouts_enabled' => true,
        ])->save();

        $this->postJson("/api/v1/admin/payout-items/{$missingItem->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.payout_item.transfer_status', 'completed');

        $this->assertSame('completed', $missingItem->refresh()->transfer_status);
        $this->assertSame('completed', $batch->refresh()->status);
    }

    public function test_admin_can_view_summary_list_details_and_cancel_safe_batch(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $org = $this->organization('Pending Org', true);
        $missingConnect = $this->organization('No Connect', false);
        $batch = PayoutBatch::create([
            'public_id' => (string) str()->uuid(),
            'batch_month' => '2026-07-01',
            'total_pool' => '10.00',
            'total_distributed' => '10.00',
            'total_organizations' => 1,
            'status' => 'pending',
        ]);
        PayoutItem::create([
            'payout_batch_id' => $batch->id,
            'organization_profile_id' => $org->id,
            'engagement_score' => '10.0000',
            'payout_amount' => '10.00',
            'total_reads' => 1,
            'total_points' => 10,
            'transfer_status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/payouts/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_payout_amount', '10.00')
            ->assertJsonPath('data.summary.organizations_awaiting_payout', 1)
            ->assertJsonPath('data.summary.organizations_missing_stripe_connect_setup', 1);

        $this->getJson('/api/v1/admin/payouts?month=2026-07&status=pending&organization='.$org->public_id.'&transfer_status=pending')
            ->assertOk()
            ->assertJsonPath('data.payout_batches.data.0.public_id', $batch->public_id);

        $this->getJson("/api/v1/admin/payouts/{$batch->public_id}")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.items.0.organization.name', 'Pending Org');

        $this->postJson("/api/v1/admin/payouts/{$batch->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.batch.status', 'canceled');

        $this->assertSame('canceled', $batch->refresh()->status);
        $this->assertSame('canceled', $batch->items()->first()->transfer_status);
        $this->assertNotNull($missingConnect);
    }

    public function test_payout_management_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/payouts')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com'));

        $this->getJson('/api/v1/admin/payouts')->assertForbidden();
        $this->postJson('/api/v1/admin/payouts/generate', ['month' => '2026-07'])->assertForbidden();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('payout_batches');
        Schema::dropIfExists('article_reads');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('organization_profiles');
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
            $table->string('stripe_connect_account_id')->nullable();
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('status')->default('published');
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
            $table->string('status')->default('active');
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
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('article_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles');
            $table->foreignId('user_id')->constrained('users');
            $table->smallInteger('read_percent')->default(0);
            $table->integer('reading_seconds')->default(0);
            $table->integer('points_earned')->default(0);
            $table->boolean('counted_for_payout')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payout_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->date('batch_month')->unique();
            $table->decimal('total_pool', 12, 2)->default(0);
            $table->decimal('total_distributed', 12, 2)->default(0);
            $table->bigInteger('total_organizations')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payout_batch_id')->constrained('payout_batches');
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->decimal('engagement_score', 12, 4);
            $table->decimal('payout_amount', 12, 2);
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->string('stripe_transfer_id')->nullable()->unique();
            $table->string('transfer_status')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['payout_batch_id', 'organization_profile_id']);
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

    private function userWithRole(string $role, string $email): User
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
            'full_name' => str($role)->headline().' User',
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

    private function organization(string $name, bool $connectReady): OrganizationProfile
    {
        $user = $this->userWithRole('organization', str($name)->slug('_').'@test.com');

        return OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => $name,
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'stripe_connect_account_id' => $connectReady ? 'acct_'.str()->random(8) : null,
            'payouts_enabled' => $connectReady,
            'charges_enabled' => $connectReady,
        ]);
    }

    private function subscription(User $user): Subscription
    {
        return Subscription::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_'.str()->random(8),
            'stripe_subscription_id' => 'sub_'.str()->random(8),
            'plan' => 'monthly',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function payment(Subscription $subscription, string $amount, string $fee, string $net, Carbon $paidAt): Payment
    {
        return Payment::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_'.str()->random(8),
            'stripe_invoice_id' => 'in_'.str()->random(8),
            'amount' => $amount,
            'stripe_fee' => $fee,
            'net_amount' => $net,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);
    }

    private function readForOrganization(OrganizationProfile $organization, User $reader, int $reads, int $points): void
    {
        $article = Article::create([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $organization->id,
            'title' => 'Story '.$organization->id,
            'slug' => 'story-'.$organization->id.'-'.str()->random(4),
            'content' => 'Content',
            'status' => 'published',
        ]);

        for ($i = 0; $i < $reads; $i++) {
            DB::table('article_reads')->insert([
                'article_id' => $article->id,
                'user_id' => $reader->id,
                'read_percent' => 100,
                'reading_seconds' => 120,
                'points_earned' => intdiv($points, $reads),
                'counted_for_payout' => true,
                'created_at' => now()->startOfMonth()->addDays(3 + $i),
            ]);
        }
    }
}
