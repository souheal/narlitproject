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

/**
 * Covers PATCH /admin/articles/{publicId} and DELETE /admin/articles/{publicId},
 * the two actions the admin articles screen calls.
 */
class AdminArticleEditDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00'));
        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_edit_article_content(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), [
            'title' => 'Original title',
            'excerpt' => 'Original excerpt',
            'category' => 'General',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", [
            'title' => 'Corrected title',
            'excerpt' => 'Corrected excerpt',
            'category' => 'Health',
            'edit_note' => 'Fixed a factual error in the opening paragraph.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Article updated successfully.')
            ->assertJsonPath('data.article.title', 'Corrected title');

        $article->refresh();
        $this->assertSame('Corrected title', $article->title);
        $this->assertSame('Corrected excerpt', $article->excerpt);
        $this->assertSame('Health', $article->category);
    }

    public function test_editing_an_article_is_written_to_the_audit_log(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), ['title' => 'Before']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", [
            'title' => 'After',
            'edit_note' => 'Title clarified for accuracy.',
        ])->assertOk();

        $log = DB::table('admin_logs')
            ->where('action', 'article.updated')
            ->where('entity_id', $article->public_id)
            ->first();

        $this->assertNotNull($log);

        $metadata = json_decode((string) $log->metadata, true);
        $this->assertSame(['title'], $metadata['fields']);
        $this->assertSame('Title clarified for accuracy.', $metadata['edit_note']);
        // the full bodies must not be copied into the audit trail
        $this->assertArrayNotHasKey('content', $metadata);
    }

    public function test_editing_content_recalculates_read_time(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), ['read_time' => 1]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", [
            'content' => str_repeat('word ', 800),
        ])->assertOk();

        $this->assertGreaterThan(1, $article->refresh()->read_time);
    }

    public function test_edit_validates_input(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile());

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", ['title' => 'ab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", ['content' => 'too short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_edit_returns_404_for_unknown_article(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com', true));

        $this->patchJson('/api/v1/admin/articles/'.str()->uuid(), ['title' => 'Whatever title'])
            ->assertNotFound();
    }

    public function test_admin_can_permanently_delete_an_article(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile());
        $articleId = $article->id;

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'Duplicate submission from the organization.',
            'confirm' => 'DELETE',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Article deleted permanently.');

        // force delete: gone for good, not just soft-deleted
        $this->assertDatabaseMissing('articles', ['id' => $articleId]);
        $this->assertNull(Article::withTrashed()->find($articleId));
    }

    public function test_delete_requires_the_confirm_token_and_a_reason(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile());

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'Duplicate submission from the organization.',
            'confirm' => 'delete',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm');

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'short',
            'confirm' => 'DELETE',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_delete_is_written_to_the_audit_log(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile(), ['title' => 'Doomed article']);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'Violates the content policy on medical claims.',
            'confirm' => 'DELETE',
        ])->assertOk();

        $log = DB::table('admin_logs')
            ->where('action', 'article.deleted')
            ->where('entity_id', $article->public_id)
            ->first();

        $this->assertNotNull($log);

        $metadata = json_decode((string) $log->metadata, true);
        $this->assertSame('Violates the content policy on medical claims.', $metadata['reason']);
        // the title survives in the log even though the row is gone
        $this->assertSame('Doomed article', $metadata['title']);
    }

    public function test_archived_articles_can_still_be_deleted(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $article = $this->article($this->organizationProfile());
        $articleId = $article->id;
        $article->delete(); // archive == soft delete

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'Cleaning out archived duplicates.',
            'confirm' => 'DELETE',
        ])->assertOk();

        $this->assertNull(Article::withTrashed()->find($articleId));
    }

    public function test_edit_and_delete_require_admin_access(): void
    {
        $article = $this->article($this->organizationProfile());

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", ['title' => 'Hijacked title'])
            ->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com', true));

        $this->patchJson("/api/v1/admin/articles/{$article->public_id}", ['title' => 'Hijacked title'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/admin/articles/{$article->public_id}", [
            'reason' => 'Trying to delete without rights.',
            'confirm' => 'DELETE',
        ])->assertForbidden();

        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
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
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('first_login_mfa_completed_at')->nullable();
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
            $table->string('verification_status')->default('pending');
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
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'certificates/test.pdf',
            'verification_status' => 'approved',
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
            'content' => 'Full article content for the test fixture.',
            'category' => 'General',
            'status' => 'pending_review',
            'read_time' => 4,
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
            'full_name' => ucfirst($role).' User',
            'username' => $role.str()->random(6),
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => $active,
            'first_login_mfa_completed_at' => now(),
        ]);
    }
}
