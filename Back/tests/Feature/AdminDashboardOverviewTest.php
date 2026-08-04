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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardOverviewTest extends TestCase
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

    public function test_admin_can_retrieve_dashboard_overview(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $subscriber = $this->userWithRole('subscriber', 'subscriber@test.com', true);
        $organizationUser = $this->userWithRole('organization', 'org@test.com', true);
        $pendingOrganizationUser = $this->userWithRole('organization', 'pending-org@test.com', false);

        $approvedOrganization = OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $organizationUser->id,
            'organization_name' => 'Approved Aid',
            'tax_id' => '123456789',
            'certificate_file' => 'certificates/approved.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDays(2),
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(2),
        ]);

        OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $pendingOrganizationUser->id,
            'organization_name' => 'Pending Aid',
            'tax_id' => '987654321',
            'certificate_file' => 'certificates/pending.pdf',
            'irs_verified' => true,
            'verification_status' => 'pending',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $publishedArticle = Article::create([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $approvedOrganization->id,
            'title' => 'Published Story',
            'slug' => 'published-story',
            'content' => 'Story content',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(2),
        ]);

        Article::create([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $approvedOrganization->id,
            'title' => 'Needs Review',
            'slug' => 'needs-review',
            'content' => 'Draft content',
            'status' => 'pending_review',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $subscription = Subscription::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'stripe_customer_id' => 'cus_dashboard',
            'stripe_subscription_id' => 'sub_dashboard',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        Payment::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscriber->id,
            'subscription_id' => $subscription->id,
            'stripe_payment_intent' => 'pi_dashboard',
            'stripe_invoice_id' => 'in_dashboard',
            'amount' => '7.00',
            'stripe_fee' => '0.30',
            'net_amount' => '6.70',
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        DB::table('article_reads')->insert([
            'article_id' => $publishedArticle->id,
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
            'organization_profile_id' => $approvedOrganization->id,
            'article_id' => $publishedArticle->id,
            'amount' => '0.07',
            'points_generated' => 10,
            'transaction_month' => now()->startOfMonth()->toDateString(),
            'created_at' => now()->subDays(4),
        ]);

        DB::table('payout_batches')->insert([
            'public_id' => (string) str()->uuid(),
            'batch_month' => now()->startOfMonth()->toDateString(),
            'total_pool' => '15.00',
            'total_distributed' => '0.00',
            'total_organizations' => 1,
            'status' => 'failed',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'organization.approved',
            'entity_type' => 'organization_profile',
            'entity_id' => $approvedOrganization->public_id,
            'created_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($admin);

        $this->assertTrue(Route::has('admin.dashboard'));
        $this->assertSame('api/v1/admin/analytics/overview', Route::getRoutes()->getByName('admin.dashboard')?->uri());

        $this->getJson('/api/v1/admin/analytics/overview?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('message', 'Analytics overview retrieved successfully.')
            ->assertJsonPath('data.metrics.article_reads', 1)
            ->assertJsonPath('data.metrics.gross_revenue', '7.00')
            ->assertJsonPath('data.metrics.net_revenue', '6.70')
            ->assertJsonPath('data.metrics.impact_amount', '0.07')
            ->assertJsonStructure([
                'data' => [
                    'metrics' => [
                        'signups',
                        'article_reads',
                        'gross_revenue',
                        'net_revenue',
                        'impact_amount',
                        'published_articles',
                    ],
                ],
            ]);
    }

    public function test_dashboard_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com', true));

        $this->getJson('/api/v1/admin/analytics/overview')->assertForbidden();
    }

    public function test_orphan_dashboard_route_is_removed(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com', true));

        $this->getJson('/api/v1/admin/dashboard')->assertNotFound();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('payout_batches');
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
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('featured_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->smallInteger('read_time')->nullable();
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_unique_reads')->default(0);
            $table->bigInteger('total_reading_seconds')->default(0);
            $table->bigInteger('total_points_generated')->default(0);
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

    private function userWithRole(string $role, string $email, bool $active): User
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
            'email_verified_at' => $active ? now() : null,
            'is_active' => $active,
            'first_login_mfa_completed_at' => $active ? now() : null,
            'failed_login_attempts' => 0,
        ]);
    }
}
