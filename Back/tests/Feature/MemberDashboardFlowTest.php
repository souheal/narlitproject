<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberDashboardFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_member_dashboard_returns_impact_stories_and_funding_breakdown(): void
    {
        [$user, $articles] = $this->seedMemberDashboardData();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.member.username', 'narlit')
            ->assertJsonPath('data.dashboard.member.subscription_active', true)
            ->assertJsonPath('data.dashboard.impact.donated_this_month', '2.31')
            ->assertJsonPath('data.dashboard.impact.articles_read', 3)
            ->assertJsonPath('data.dashboard.impact.nonprofits_funded_this_month', 3)
            ->assertJsonPath('data.dashboard.impact.day_streak', 2)
            ->assertJsonPath('data.dashboard.stories.0.public_id', $articles[0]->public_id)
            ->assertJsonPath('data.dashboard.stories.0.is_read', true)
            ->assertJsonPath('data.dashboard.stories.0.cta_label', 'Read again')
            ->assertJsonPath('data.dashboard.subscription_breakdown.subscription_amount', '7.00')
            ->assertJsonPath('data.dashboard.subscription_breakdown.nonprofits.percent', 33)
            ->assertJsonPath('data.dashboard.funding_breakdown.0.reads', 1);

        $this->getJson('/api/v1/member/articles?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $articles[0]->public_id)
            ->assertJsonPath('data.articles.per_page', 2);
    }

    public function test_member_can_record_article_read_and_generate_impact(): void
    {
        [$user, $articles] = $this->seedMemberDashboardData();
        $article = $articles[1];

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/member/articles/{$article->public_id}/read", [
            'read_percent' => 100,
            'reading_seconds' => 240,
            'session_id' => 'session-123',
            'device_type' => 'desktop',
            'country' => 'US',
        ])
            ->assertCreated()
            ->assertJsonPath('data.read.read_percent', 100)
            ->assertJsonPath('data.read.counted_for_payout', true)
            ->assertJsonPath('data.impact_transaction.amount', '0.07');

        $this->assertDatabaseHas('article_reads', [
            'article_id' => $article->id,
            'user_id' => $user->id,
            'read_percent' => 100,
            'counted_for_payout' => true,
        ]);

        $this->assertDatabaseHas('impact_transactions', [
            'article_id' => $article->id,
            'user_id' => $user->id,
            'amount' => '0.07',
        ]);

        $this->assertDatabaseHas('impact_wallets', [
            'user_id' => $user->id,
            'total_articles_read' => 4,
            'total_organizations_supported' => 3,
        ]);
    }

    private function seedMemberDashboardData(): array
    {
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'subscriber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::uuid(),
            'role_id' => 1,
            'full_name' => 'NarLit User',
            'username' => 'narlit',
            'email' => 'narlit@test.com',
            'phone' => '09370000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        DB::table('subscriptions')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $organizations = collect([
            ['name' => 'CAIR', 'category' => 'Civil Rights'],
            ['name' => 'Feeding America', 'category' => 'Food Security'],
            ['name' => 'Doctors Without Borders', 'category' => 'Healthcare'],
        ])->map(function (array $organization, int $index) use ($user): array {
            DB::table('organization_profiles')->insert([
                'id' => $index + 1,
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_name' => $organization['name'],
                'tax_id' => '12345678'.$index,
                'irs_verified' => true,
                'verification_status' => 'approved',
                'payouts_enabled' => false,
                'charges_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $index + 1,
                'name' => $organization['name'],
                'category' => $organization['category'],
            ];
        });

        $articles = $organizations->map(function (array $organization, int $index): Article {
            return Article::query()->create([
                'public_id' => (string) Str::uuid(),
                'organization_profile_id' => $organization['id'],
                'title' => [
                    'How Legal Aid Is Changing Lives in Underserved Communities',
                    'The Hidden Crisis: Food Insecurity Among College Students',
                    'Field Notes: Bringing Emergency Medicine to Remote Regions',
                ][$index],
                'slug' => 'member-dashboard-article-'.$index,
                'excerpt' => 'A short story preview for the member dashboard.',
                'content' => 'Full story body.',
                'category' => $organization['category'],
                'status' => 'published',
                'featured_at' => now()->subMinutes($index),
                'published_at' => now()->subDays($index),
                'read_time' => [5, 4, 6][$index],
            ]);
        })->values();

        foreach ($articles as $index => $article) {
            DB::table('article_reads')->insert([
                'article_id' => $article->id,
                'user_id' => $user->id,
                'read_percent' => 100,
                'reading_seconds' => 300,
                'points_earned' => 10,
                'counted_for_payout' => true,
                'created_at' => $index === 0 ? now() : now()->subDay(),
            ]);

            DB::table('impact_transactions')->insert([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_profile_id' => $article->organization_profile_id,
                'article_id' => $article->id,
                'amount' => '0.77',
                'points_generated' => 10,
                'transaction_month' => now()->startOfMonth()->toDateString(),
                'created_at' => now(),
            ]);
        }

        return [$user, $articles];
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('impact_wallets');
        Schema::dropIfExists('impact_transactions');
        Schema::dropIfExists('article_reads');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('subscriptions');
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
            $table->foreignId('user_id')->constrained('users');
            $table->string('organization_name');
            $table->string('website')->nullable();
            $table->string('landline')->nullable();
            $table->string('tax_id')->unique();
            $table->string('certificate_file')->nullable();
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

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category', 120)->nullable();
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
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

        Schema::create('article_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles');
            $table->foreignId('user_id')->constrained('users');
            $table->smallInteger('read_percent')->default(0);
            $table->integer('reading_seconds')->default(0);
            $table->integer('points_earned')->default(0);
            $table->boolean('counted_for_payout')->default(false);
            $table->string('session_id', 128)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('impact_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->foreignId('article_id')->nullable()->constrained('articles');
            $table->foreignId('payment_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->bigInteger('points_generated')->default(0);
            $table->date('transaction_month');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('impact_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->decimal('total_impact_amount', 12, 2)->default(0);
            $table->bigInteger('total_articles_read')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->bigInteger('total_organizations_supported')->default(0);
            $table->timestamps();
        });
    }
}
