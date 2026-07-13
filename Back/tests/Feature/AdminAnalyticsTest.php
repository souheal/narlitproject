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

class AdminAnalyticsTest extends TestCase
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

    public function test_admin_can_retrieve_analytics_datasets(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', now()->subDays(12));
        $reader = $this->userWithRole('subscriber', 'reader@test.com', now()->subDays(10));
        $organization = $this->organization('Health Org');
        $article = $this->article($organization, 'Clean Water', 'Health');
        $subscription = $this->subscription($reader);
        $this->payment($subscription, '10.00', '0.50', '9.50', now()->subDays(5));
        $this->read($article, $reader, now()->subDays(4), 100, 120, 10);
        $this->impact($article, $reader, $organization, '0.70', now()->subDays(4));
        DB::table('payout_batches')->insert([
            'public_id' => (string) str()->uuid(),
            'batch_month' => now()->startOfMonth()->toDateString(),
            'total_pool' => '1.00',
            'total_distributed' => '1.00',
            'total_organizations' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payout_items')->insert([
            'payout_batch_id' => 1,
            'organization_profile_id' => $organization->id,
            'engagement_score' => '10.0000',
            'payout_amount' => '1.00',
            'total_reads' => 1,
            'total_points' => 10,
            'transfer_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $range = 'date_from=2026-07-01&date_to=2026-07-13&interval=day';

        $this->getJson("/api/v1/admin/analytics/overview?{$range}&compare=1")
            ->assertOk()
            ->assertJsonPath('data.metrics.signups', 3)
            ->assertJsonPath('data.metrics.article_reads', 1)
            ->assertJsonPath('data.metrics.gross_revenue', '10.00')
            ->assertJsonPath('data.metrics.net_revenue', '9.50')
            ->assertJsonPath('data.metrics.impact_amount', '0.70')
            ->assertJsonPath('data.comparison.signups', 0);

        $this->getJson("/api/v1/admin/analytics/timeseries?{$range}")
            ->assertOk()
            ->assertJsonPath('data.series.article_reads.8.bucket', '2026-07-09')
            ->assertJsonPath('data.series.article_reads.8.value', 1)
            ->assertJsonPath('data.series.revenue.7.value', '10.00')
            ->assertJsonPath('data.series.impact_amount.8.value', '0.70');

        $this->getJson("/api/v1/admin/analytics/top-organizations?{$range}")
            ->assertOk()
            ->assertJsonPath('data.organizations.0.organization_name', 'Health Org')
            ->assertJsonPath('data.organizations.0.reads', 1)
            ->assertJsonPath('data.organizations.0.impact_generated', '0.70')
            ->assertJsonPath('data.organizations.0.payout_amount', '1.00');

        $this->getJson("/api/v1/admin/analytics/top-articles?{$range}")
            ->assertOk()
            ->assertJsonPath('data.articles.0.title', 'Clean Water')
            ->assertJsonPath('data.articles.0.reads', 1)
            ->assertJsonPath('data.articles.0.unique_readers', 1)
            ->assertJsonPath('data.articles.0.reading_seconds', 120)
            ->assertJsonPath('data.articles.0.impact_generated', '0.70');

        $this->getJson("/api/v1/admin/analytics/categories?{$range}")
            ->assertOk()
            ->assertJsonPath('data.categories.0.category', 'Health')
            ->assertJsonPath('data.categories.0.reads', 1)
            ->assertJsonPath('data.categories.0.articles', 1)
            ->assertJsonPath('data.categories.0.impact_amount', '0.70');

        $this->getJson("/api/v1/admin/analytics/funnel?{$range}")
            ->assertOk()
            ->assertJsonPath('data.steps.0.key', 'registration_started')
            ->assertJsonPath('data.steps.0.count', 3)
            ->assertJsonPath('data.steps.2.key', 'payment_completed')
            ->assertJsonPath('data.steps.2.count', 1)
            ->assertJsonPath('data.steps.5.key', 'first_article_read')
            ->assertJsonPath('data.steps.5.count', 1);

        $this->getJson('/api/v1/admin/analytics/cohorts?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.cohorts.0.cohort_month', '2026-07')
            ->assertJsonPath('data.cohorts.0.cohort_size', 3)
            ->assertJsonPath('data.cohorts.0.retention.0.active_readers', 1);
    }

    public function test_analytics_validates_query_parameters(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com', now()));

        $this->getJson('/api/v1/admin/analytics/timeseries?interval=hour')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interval']);

        $this->getJson('/api/v1/admin/analytics/top-articles?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_analytics_requires_admin_access(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com', now()));

        $this->getJson('/api/v1/admin/analytics/overview')->assertForbidden();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('payout_items');
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
            $table->boolean('is_active')->default(true);
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
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
            $table->boolean('irs_verified')->default(true);
            $table->string('verification_status')->default('approved');
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
            $table->string('category')->nullable();
            $table->string('status')->default('published');
            $table->timestamp('published_at')->nullable();
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_unique_reads')->default(0);
            $table->bigInteger('total_reading_seconds')->default(0);
            $table->bigInteger('total_points_generated')->default(0);
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
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->string('stripe_payment_intent')->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('stripe_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
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
            $table->decimal('amount', 12, 2);
            $table->bigInteger('points_generated')->default(0);
            $table->date('transaction_month');
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
            $table->string('transfer_status')->nullable();
            $table->timestamps();
        });
    }

    private function userWithRole(string $role, string $email, Carbon $createdAt): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId(['name' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => str($role)->headline().' User',
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => $createdAt->copy()->addHour(),
            'is_active' => true,
            'first_login_mfa_completed_at' => $createdAt->copy()->addHours(3),
            'last_login_at' => $createdAt->copy()->addHours(2),
            'failed_login_attempts' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function organization(string $name): OrganizationProfile
    {
        $user = $this->userWithRole('organization', str($name)->slug('_').'@test.com', now()->subDays(9));

        return OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => $name,
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
        ]);
    }

    private function article(OrganizationProfile $organization, string $title, string $category): Article
    {
        return Article::create([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $organization->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->random(4),
            'content' => 'Content',
            'category' => $category,
            'status' => 'published',
            'published_at' => now()->subDays(6),
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
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
            'amount' => '10.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDays(6),
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
            'amount' => $amount,
            'stripe_fee' => $fee,
            'net_amount' => $net,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);
    }

    private function read(Article $article, User $reader, Carbon $createdAt, int $percent, int $seconds, int $points): void
    {
        DB::table('article_reads')->insert([
            'article_id' => $article->id,
            'user_id' => $reader->id,
            'read_percent' => $percent,
            'reading_seconds' => $seconds,
            'points_earned' => $points,
            'counted_for_payout' => true,
            'created_at' => $createdAt,
        ]);
    }

    private function impact(Article $article, User $reader, OrganizationProfile $organization, string $amount, Carbon $createdAt): void
    {
        DB::table('impact_transactions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $reader->id,
            'organization_profile_id' => $organization->id,
            'article_id' => $article->id,
            'amount' => $amount,
            'points_generated' => 10,
            'transaction_month' => $createdAt->copy()->startOfMonth()->toDateString(),
            'created_at' => $createdAt,
        ]);
    }
}
