<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_list_users_with_filters_sorting_and_pagination(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $subscriber = $this->userWithRole('subscriber', 'member@test.com', true, [
            'full_name' => 'Member One',
            'phone' => '+15550000001',
            'last_login_at' => now()->subDay(),
            'last_login_ip' => '127.0.0.1',
        ]);
        $this->userWithRole('subscriber', 'inactive@test.com', false, [
            'full_name' => 'Inactive Member',
        ]);

        Subscription::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'stripe_customer_id' => 'cus_member',
            'stripe_subscription_id' => 'sub_member',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users?search=member@test.com&role=subscriber&account_status=active&email_verified=1&subscription_status=active&mfa_status=completed&sort=name&direction=asc&per_page=5')
            ->assertOk()
            ->assertJsonPath('message', 'Users retrieved successfully.')
            ->assertJsonPath('data.users.data.0.public_id', $subscriber->public_id)
            ->assertJsonPath('data.users.data.0.full_name', 'Member One')
            ->assertJsonPath('data.users.data.0.role', 'subscriber')
            ->assertJsonPath('data.users.data.0.subscription_status', 'active')
            ->assertJsonPath('data.users.data.0.email_verified', true)
            ->assertJsonPath('data.users.data.0.phone_mfa_completed', true)
            ->assertJsonPath('data.users.meta.per_page', 5)
            ->assertJsonMissingPath('data.users.data.0.password')
            ->assertJsonMissingPath('data.users.data.0.otp_code')
            ->assertJsonMissingPath('data.users.data.0.phone_mfa_code');
    }

    public function test_admin_can_view_user_details_without_sensitive_fields(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $subscriber = $this->seedUserDetailsFixture($admin);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/users/{$subscriber->public_id}")
            ->assertOk()
            ->assertJsonPath('data.user.profile.public_id', $subscriber->public_id)
            ->assertJsonPath('data.user.subscription.status', 'active')
            ->assertJsonPath('data.user.payment_summary.total_paid', '7.00')
            ->assertJsonPath('data.user.read_impact_summary.total_reads', 1)
            ->assertJsonPath('data.user.read_impact_summary.total_impact_amount', '0.07')
            ->assertJsonPath('data.user.recent_login.last_login_ip', '127.0.0.1')
            ->assertJsonPath('data.user.admin_action_history.0.action', 'user.suspended')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.password_reset_otp_code')
            ->assertJsonMissingPath('data.user.phone_mfa_code');
    }

    public function test_admin_can_suspend_activate_reset_mfa_send_password_reset_and_revoke_tokens(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $subscriber = $this->userWithRole('subscriber', 'member@test.com', true);
        $subscriber->createToken('web');
        $subscriber->createToken('mobile');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$subscriber->public_id}/status", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.account_status', 'suspended');

        $this->assertFalse($subscriber->refresh()->is_active);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'user.suspended',
            'entity_type' => 'user',
            'entity_id' => $subscriber->public_id,
        ]);

        $this->patchJson("/api/v1/admin/users/{$subscriber->public_id}/status", [
            'is_active' => true,
        ])->assertOk();

        $this->postJson("/api/v1/admin/users/{$subscriber->public_id}/send-password-reset")
            ->assertOk()
            ->assertJsonPath('message', 'Password reset email sent successfully.');
        $this->assertNotNull($subscriber->refresh()->password_reset_otp_code);

        $this->postJson("/api/v1/admin/users/{$subscriber->public_id}/reset-mfa")
            ->assertOk()
            ->assertJsonPath('data.user.phone_mfa_completed', false);
        $this->assertNull($subscriber->refresh()->first_login_mfa_completed_at);

        $this->deleteJson("/api/v1/admin/users/{$subscriber->public_id}/tokens")
            ->assertOk()
            ->assertJsonPath('data.tokens_revoked', 2);
        $this->assertSame(0, $subscriber->tokens()->count());
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$admin->public_id}/status", [
            'is_active' => false,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot suspend your own active admin session.');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_users_management_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com', true));

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    private function seedUserDetailsFixture(User $admin): User
    {
        $subscriber = $this->userWithRole('subscriber', 'member@test.com', true, [
            'full_name' => 'Member One',
            'last_login_at' => now()->subDay(),
            'last_login_ip' => '127.0.0.1',
        ]);
        $organizationUser = $this->userWithRole('organization', 'org@test.com', true);

        $organization = OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $organizationUser->id,
            'organization_name' => 'Approved Aid',
            'tax_id' => '123456789',
            'certificate_file' => 'certificates/approved.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDays(3),
        ]);

        $article = Article::create([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $organization->id,
            'title' => 'Impact Story',
            'slug' => 'impact-story',
            'content' => 'Story content',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        $subscription = Subscription::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'stripe_customer_id' => 'cus_member',
            'stripe_subscription_id' => 'sub_member',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        Payment::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_member',
            'stripe_invoice_id' => 'in_member',
            'amount' => '7.00',
            'stripe_fee' => '0.30',
            'net_amount' => '6.70',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->subDays(5),
        ]);

        DB::table('article_reads')->insert([
            'article_id' => $article->id,
            'user_id' => $subscriber->id,
            'read_percent' => 100,
            'reading_seconds' => 180,
            'points_earned' => 10,
            'counted_for_payout' => true,
            'created_at' => now()->subDays(4),
        ]);

        DB::table('impact_transactions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'organization_profile_id' => $organization->id,
            'article_id' => $article->id,
            'amount' => '0.07',
            'points_generated' => 10,
            'transaction_month' => now()->startOfMonth()->toDateString(),
            'created_at' => now()->subDays(4),
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'user.suspended',
            'entity_type' => 'user',
            'entity_id' => $subscriber->public_id,
            'created_at' => now()->subDays(2),
        ]);

        return $subscriber;
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('impact_transactions');
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
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone_mfa_code')->nullable();
            $table->timestamp('phone_mfa_expires_at')->nullable();
            $table->timestamp('phone_mfa_verified_at')->nullable();
            $table->string('password_reset_otp_code')->nullable();
            $table->timestamp('password_reset_otp_expires_at')->nullable();
            $table->timestamp('password_reset_otp_verified_at')->nullable();
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
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
            $table->text('rejection_reason')->nullable();
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
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
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

        Schema::create('impact_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->foreignId('article_id')->nullable()->constrained('articles');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->decimal('amount', 12, 2);
            $table->bigInteger('points_generated')->default(0);
            $table->date('transaction_month');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
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

    private function userWithRole(string $role, string $email, bool $active, array $overrides = []): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::create(array_merge([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => str($role)->headline().' User',
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => $active ? now() : null,
            'is_active' => $active,
            'first_login_mfa_completed_at' => $active ? now() : null,
            'failed_login_attempts' => 0,
        ], $overrides));
    }
}
