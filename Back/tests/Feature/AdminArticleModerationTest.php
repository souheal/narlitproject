<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminArticleModerationTest extends TestCase
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

    public function test_admin_can_list_and_preview_articles(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $organization = $this->organizationProfile();
        $article = $this->article($organization, [
            'title' => 'Clean Water Story',
            'category' => 'Health',
            'status' => 'pending_review',
            'content' => str_repeat('word ', 401),
            'total_reads' => 12,
            'total_unique_reads' => 7,
            'total_points_generated' => 42,
            'featured_at' => now()->subHour(),
            'metadata' => ['images' => ['https://example.test/water.jpg']],
        ]);

        DB::table('article_reads')->insert([
            'article_id' => $article->id,
            'user_id' => $admin->id,
            'read_percent' => 100,
            'reading_seconds' => 90,
            'points_earned' => 5,
            'counted_for_payout' => true,
            'created_at' => now()->subDay(),
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'article.submitted',
            'entity_type' => 'article',
            'entity_id' => $article->public_id,
            'created_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/articles?status=pending_review&search=water&category=Health&sort=reads&direction=desc&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id)
            ->assertJsonPath('data.articles.data.0.organization.name', 'NarLit Aid')
            ->assertJsonPath('data.articles.data.0.author.email', 'org@test.com')
            ->assertJsonPath('data.articles.data.0.total_reads', 12)
            ->assertJsonPath('data.articles.data.0.featured', true)
            ->assertJsonPath('data.articles.data.0.is_featured', true)
            ->assertJsonPath('data.articles.data.0.read_time_minutes', 3)
            ->assertJsonPath('data.articles.data.0.total_unique_reads', 7)
            ->assertJsonPath('data.articles.data.0.total_points_generated', 42)
            ->assertJsonPath('data.articles.meta.per_page', 5);

        $this->getJson("/api/v1/admin/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Clean Water Story')
            ->assertJsonPath('data.article.content', str_repeat('word ', 401))
            ->assertJsonPath('data.article.body', str_repeat('word ', 401))
            ->assertJsonPath('data.article.images.0', 'https://example.test/water.jpg')
            ->assertJsonPath('data.article.read_statistics.recorded_read_events', 1)
            ->assertJsonPath('data.article.submission_history.0.action', 'article.submitted');
    }

    public function test_admin_article_detail_returns_complete_body_for_moderation_preview(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $body = '<h1>Editorial title</h1><p>'.str_repeat('Complete article content ', 260).'</p><blockquote>Keep formatting.</blockquote>';
        $article = $this->article($this->organizationProfile(), [
            'title' => 'Long Pending Story',
            'slug' => 'long-pending-story',
            'status' => 'pending_review',
            'content' => $body,
            'excerpt' => 'Short excerpt only.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.public_id', $article->public_id)
            ->assertJsonPath('data.article.title', 'Long Pending Story')
            ->assertJsonPath('data.article.excerpt', 'Short excerpt only.')
            ->assertJsonPath('data.article.content', $body)
            ->assertJsonPath('data.article.body', $body)
            ->assertJsonPath('data.article.status', 'pending_review')
            ->assertJsonPath('data.article.organization.name', 'NarLit Aid')
            ->assertJsonPath('data.article.read_statistics.total_reads', 0)
            ->assertJsonPath('data.article.dates.submitted_at', $article->created_at?->toIso8601String());

        $this->assertSame(strlen($body), strlen($this->getJson("/api/v1/admin/articles/{$article->public_id}")->json('data.article.body')));
    }

    public function test_admin_article_detail_returns_body_for_draft_and_rejected_articles(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $draft = $this->article($this->organizationProfile(), [
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'status' => 'draft',
            'content' => '<p>Draft full body.</p>',
        ]);
        $rejected = $this->article($this->organizationProfile('Second Org', 'second-org@test.com'), [
            'title' => 'Rejected Story',
            'slug' => 'rejected-story',
            'status' => 'rejected',
            'content' => '<p>Rejected full body.</p>',
            'rejection_reason' => 'Needs sources.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/articles/{$draft->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.body', '<p>Draft full body.</p>')
            ->assertJsonPath('data.article.status', 'draft');

        $this->getJson("/api/v1/admin/articles/{$rejected->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.body', '<p>Rejected full body.</p>')
            ->assertJsonPath('data.article.status', 'rejected')
            ->assertJsonPath('data.article.rejection_reason', 'Needs sources.');
    }

    public function test_admin_article_list_does_not_include_full_body_or_content(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), [
            'title' => 'Pending Story',
            'slug' => 'pending-story-list-no-body',
            'status' => 'pending_review',
            'content' => '<p>Full body should only appear in detail.</p>',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/articles?status=pending_review&search=Pending')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id)
            ->assertJsonMissingPath('data.articles.data.0.body')
            ->assertJsonMissingPath('data.articles.data.0.content');
    }

    public function test_admin_article_list_resource_returns_feature_read_time_and_counter_defaults_without_n_plus_one(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $organization = $this->organizationProfile();
        $featured = $this->article($organization, [
            'title' => 'Featured HTML Story',
            'slug' => 'featured-html-story',
            'status' => 'published',
            'content' => '<p>'.str_repeat('word ', 200).'</p><strong>'.str_repeat('impact ', 1).'</strong>',
            'featured_at' => now(),
            'published_at' => now()->subDay(),
            'total_unique_reads' => 125,
            'total_points_generated' => 420,
        ]);
        $empty = $this->article($organization, [
            'title' => 'Empty Story',
            'slug' => 'empty-story',
            'status' => 'published',
            'content' => '',
            'featured_at' => null,
            'published_at' => now()->subDays(2),
            'total_unique_reads' => 0,
            'total_points_generated' => 0,
        ]);

        Sanctum::actingAs($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/admin/articles?status=published&sort=title&direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $empty->public_id)
            ->assertJsonPath('data.articles.data.0.title', 'Empty Story')
            ->assertJsonPath('data.articles.data.0.is_featured', false)
            ->assertJsonPath('data.articles.data.0.read_time_minutes', 0)
            ->assertJsonPath('data.articles.data.0.total_unique_reads', 0)
            ->assertJsonPath('data.articles.data.0.total_points_generated', 0)
            ->assertJsonPath('data.articles.data.1.public_id', $featured->public_id)
            ->assertJsonPath('data.articles.data.1.is_featured', true)
            ->assertJsonPath('data.articles.data.1.featured', true)
            ->assertJsonPath('data.articles.data.1.read_time_minutes', 2)
            ->assertJsonPath('data.articles.data.1.total_unique_reads', 125)
            ->assertJsonPath('data.articles.data.1.total_points_generated', 420)
            ->assertJsonPath('data.articles.data.1.status', 'published');

        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
    }

    public function test_admin_article_list_read_time_returns_minimum_one_for_non_empty_content(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), [
            'title' => 'Short Story',
            'slug' => 'short-story',
            'status' => 'pending_review',
            'content' => '<p>Hello world</p>',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/articles?status=pending_review&search=Short')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id)
            ->assertJsonPath('data.articles.data.0.is_featured', false)
            ->assertJsonPath('data.articles.data.0.read_time_minutes', 1);
    }

    public function test_admin_can_approve_publish_feature_unfeature_archive_and_restore_article(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), [
            'status' => 'pending_review',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/articles/{$article->public_id}/approve")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'published')
            ->assertJsonPath('data.article.featured', false);

        $this->assertSame('published', $article->refresh()->status);
        $this->assertNotNull($article->published_at);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'article.approved',
            'entity_type' => 'article',
            'entity_id' => $article->public_id,
        ]);

        $this->postJson("/api/v1/admin/articles/{$article->public_id}/feature")
            ->assertOk()
            ->assertJsonPath('data.article.featured', true);
        $this->assertNotNull($article->refresh()->featured_at);

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}/feature")
            ->assertOk()
            ->assertJsonPath('data.article.featured', false);
        $this->assertNull($article->refresh()->featured_at);

        $this->postJson("/api/v1/admin/articles/{$article->public_id}/archive")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'archived');
        $this->assertSoftDeleted('articles', ['id' => $article->id]);

        $this->getJson('/api/v1/admin/articles?status=archived')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id)
            ->assertJsonPath('data.articles.data.0.status', 'archived');

        $this->postJson("/api/v1/admin/articles/{$article->public_id}/restore")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'published');
        $this->assertFalse($article->refresh()->trashed());
    }

    public function test_reject_and_request_changes_require_reason_and_prevent_invalid_transitions(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $pending = $this->article($this->organizationProfile(), [
            'status' => 'pending_review',
            'slug' => 'pending-story',
        ]);
        $published = $this->article($this->organizationProfile('Second Org', 'second-org@test.com'), [
            'status' => 'published',
            'slug' => 'published-story',
            'published_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/articles/{$pending->public_id}/reject")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/admin/articles/{$published->public_id}/reject", [
            'reason' => 'This published article should not be rejected directly.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only draft or pending articles can be rejected.');

        $this->postJson("/api/v1/admin/articles/{$pending->public_id}/request-changes", [
            'reason' => 'Please add more source information before publication.',
        ])
            ->assertOk()
            ->assertJsonPath('data.article.status', 'draft');

        $this->assertSame('draft', $pending->refresh()->status);
        $this->assertSame('Please add more source information before publication.', $pending->rejection_reason);
        $this->assertNotNull($pending->metadata['last_change_request'] ?? null);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'article.changes_requested',
            'entity_type' => 'article',
            'entity_id' => $pending->public_id,
        ]);
    }

    public function test_only_published_articles_can_be_featured(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $draft = $this->article($this->organizationProfile(), [
            'status' => 'draft',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/articles/{$draft->public_id}/feature")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only published articles can be featured.');
    }

    public function test_article_moderation_requires_admin_access(): void
    {
        $article = $this->article($this->organizationProfile(), [
            'status' => 'pending_review',
        ]);

        $this->getJson('/api/v1/admin/articles')->assertUnauthorized();
        $this->getJson("/api/v1/admin/articles/{$article->public_id}")->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com', true));

        $this->getJson('/api/v1/admin/articles')->assertForbidden();
        $this->getJson("/api/v1/admin/articles/{$article->public_id}")->assertForbidden();
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/approve")->assertForbidden();
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('article_reads');
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
            $table->string('website')->nullable();
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
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category')->nullable();
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

    private function organizationProfile(string $name = 'NarLit Aid', string $email = 'org@test.com'): OrganizationProfile
    {
        $user = $this->userWithRole('organization', $email, true);

        return OrganizationProfile::create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => $name,
            'website' => 'https://example.test',
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'reviewed_at' => now()->subDays(10),
        ]);
    }

    private function article(OrganizationProfile $organization, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $organization->id,
            'title' => 'Article Title',
            'slug' => 'article-'.str()->random(8),
            'excerpt' => 'Short excerpt',
            'content' => 'Full article content.',
            'category' => 'General',
            'status' => 'draft',
            'read_time' => 4,
            'total_reads' => 0,
            'total_unique_reads' => 0,
            'total_reading_seconds' => 0,
            'total_points_generated' => 0,
        ], $overrides));
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
