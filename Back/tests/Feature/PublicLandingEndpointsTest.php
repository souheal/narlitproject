<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the two public endpoints the marketing landing page depends on:
 * GET /articles/featured and GET /settings/public.
 */
class PublicLandingEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00'));
        $this->createTestSchema();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_can_fetch_featured_articles(): void
    {
        $organization = $this->organizationProfile();
        $featured = $this->article($organization, [
            'title' => 'Featured Story',
            'status' => 'published',
            'published_at' => now()->subDays(5),
            'featured_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/articles/featured')
            ->assertOk()
            ->assertJsonPath('message', 'Featured articles retrieved.')
            ->assertJsonPath('data.articles.0.public_id', $featured->public_id)
            ->assertJsonPath('data.articles.0.organization.name', 'NarLit Aid');
    }

    public function test_featured_articles_sort_featured_first_then_newest(): void
    {
        $organization = $this->organizationProfile();

        $oldFeatured = $this->article($organization, [
            'title' => 'Older but featured',
            'status' => 'published',
            'published_at' => now()->subDays(30),
            'featured_at' => now()->subDay(),
        ]);
        $newestPlain = $this->article($organization, [
            'title' => 'Newest, not featured',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/articles/featured')
            ->assertOk()
            ->assertJsonPath('data.articles.0.public_id', $oldFeatured->public_id)
            ->assertJsonPath('data.articles.1.public_id', $newestPlain->public_id);
    }

    public function test_featured_articles_exclude_unpublished_and_respect_limit(): void
    {
        $organization = $this->organizationProfile();

        $this->article($organization, ['title' => 'Draft', 'status' => 'draft']);
        $this->article($organization, ['title' => 'Pending', 'status' => 'pending_review']);

        foreach (range(1, 4) as $i) {
            $this->article($organization, [
                'title' => "Published {$i}",
                'status' => 'published',
                'published_at' => now()->subDays($i),
            ]);
        }

        $this->getJson('/api/v1/articles/featured?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.articles');

        // drafts must never surface publicly
        $all = $this->getJson('/api/v1/articles/featured?limit=12')->json('data.articles');
        $this->assertCount(4, $all);
        $this->assertNotContains('Draft', array_column($all, 'title'));
        $this->assertNotContains('Pending', array_column($all, 'title'));
    }

    public function test_featured_route_is_not_shadowed_by_the_article_wildcard(): void
    {
        $organization = $this->organizationProfile();
        $this->article($organization, [
            'status' => 'published',
            'published_at' => now(),
        ]);

        // If /articles/{publicId} were registered first, "featured" would be treated
        // as a public id and this would 404.
        $this->getJson('/api/v1/articles/featured')
            ->assertOk()
            ->assertJsonStructure(['data' => ['articles']]);
    }

    public function test_featured_limit_is_clamped_to_a_sane_range(): void
    {
        $organization = $this->organizationProfile();

        foreach (range(1, 15) as $i) {
            $this->article($organization, [
                'title' => "Published {$i}",
                'status' => 'published',
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $this->getJson('/api/v1/articles/featured?limit=999')
            ->assertOk()
            ->assertJsonCount(12, 'data.articles');

        $this->getJson('/api/v1/articles/featured?limit=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles');
    }

    public function test_guest_can_fetch_public_settings(): void
    {
        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('message', 'Public settings retrieved.')
            ->assertJsonPath('data.impact_split.nonprofit_percentage', 33)
            ->assertJsonPath('data.impact_split.operations_percentage', 33)
            ->assertJsonPath('data.impact_split.growth_percentage', 34);
    }

    public function test_public_settings_reflect_stored_overrides(): void
    {
        PlatformSetting::create([
            'key' => 'impact_split.nonprofit_percentage',
            'value' => ['value' => 50],
            'group' => 'impact_split',
            'type' => 'integer',
            'is_public' => false,
        ]);

        Cache::flush();

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.impact_split.nonprofit_percentage', 50);
    }

    public function test_public_settings_do_not_leak_other_groups(): void
    {
        $response = $this->getJson('/api/v1/settings/public')->assertOk();

        $data = $response->json('data');

        $this->assertSame(['impact_split'], array_keys($data));
        $this->assertStringNotContainsString('stripe', strtolower((string) json_encode($data)));
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('organization_profiles');
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
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
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

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('group');
            $table->string('type');
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function organizationProfile(string $name = 'NarLit Aid', string $email = 'org@test.com'): OrganizationProfile
    {
        $roleId = DB::table('roles')->where('name', 'organization')->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => 'organization',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $user = User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => $name.' Owner',
            'username' => 'org'.str()->random(6),
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

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
            'content' => 'Full article content.',
            'category' => 'General',
            'status' => 'draft',
            'read_time' => 4,
        ], $overrides));
    }
}
