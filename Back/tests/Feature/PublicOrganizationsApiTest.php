<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicOrganizationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'subscriber', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'organization', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_organization_index_remains_accessible_anonymously_and_preserves_public_fields(): void
    {
        $organization = $this->organization('Public Aid', [
            'website' => 'https://public-aid.test',
            'metadata' => [
                'description' => 'Community support work.',
                'logo_url' => 'https://cdn.test/public-aid.png',
            ],
        ]);

        $this->article($organization, ['category' => 'Health', 'total_reads' => 7]);
        $this->article($organization, ['category' => 'Education', 'total_reads' => 3]);

        $response = $this->getJson('/api/v1/organizations');

        $response
            ->assertOk()
            ->assertJsonPath('data.organizations.data.0.public_id', $organization->public_id)
            ->assertJsonPath('data.organizations.data.0.name', 'Public Aid')
            ->assertJsonPath('data.organizations.data.0.verification_status', 'approved')
            ->assertJsonPath('data.organizations.data.0.website', 'https://public-aid.test')
            ->assertJsonPath('data.organizations.data.0.logo_url', 'https://cdn.test/public-aid.png')
            ->assertJsonPath('data.organizations.data.0.published_articles_count', 2)
            ->assertJsonPath('data.organizations.data.0.total_reads', 10)
            ->assertJsonPath('data.organizations.data.0.description', 'Community support work.')
            ->assertJsonPath('data.organizations.data.0.is_supported_by_me', false)
            ->assertJsonStructure([
                'data' => [
                    'organizations' => [
                        'data' => [[
                            'public_id',
                            'name',
                            'verification_status',
                            'website',
                            'logo_url',
                            'published_articles_count',
                            'total_reads',
                            'description',
                            'categories',
                            'supporters_count',
                            'is_supported_by_me',
                        ]],
                    ],
                    'categories',
                ],
            ]);
    }

    public function test_organization_detail_remains_accessible_anonymously_and_exposes_required_fields(): void
    {
        $organization = $this->organization('Detail Aid');

        $this->article($organization, ['category' => 'Food Security', 'total_reads' => 11]);

        $this->getJson("/api/v1/organizations/{$organization->public_id}")
            ->assertOk()
            ->assertJsonPath('data.organization.public_id', $organization->public_id)
            ->assertJsonPath('data.organization.name', 'Detail Aid')
            ->assertJsonPath('data.organization.categories', ['Food Security'])
            ->assertJsonPath('data.organization.supporters_count', 0)
            ->assertJsonPath('data.organization.is_supported_by_me', false)
            ->assertJsonStructure([
                'data' => [
                    'organization' => [
                        'public_id',
                        'name',
                        'verification_status',
                        'website',
                        'logo_url',
                        'published_articles_count',
                        'total_reads',
                        'description',
                        'categories',
                        'supporters_count',
                        'is_supported_by_me',
                    ],
                ],
            ]);
    }

    public function test_authenticated_support_state_is_user_specific(): void
    {
        $organization = $this->organization('Supported Aid');
        $article = $this->article($organization);
        $supportingUser = $this->user('supporter@test.com');
        $unsupportedUser = $this->user('other@test.com');

        $this->impact($organization, $supportingUser, $article);

        Sanctum::actingAs($supportingUser);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonPath('data.organizations.data.0.is_supported_by_me', true);

        Sanctum::actingAs($unsupportedUser);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonPath('data.organizations.data.0.is_supported_by_me', false);
    }

    public function test_supporters_count_uses_distinct_users_not_transaction_rows(): void
    {
        $organization = $this->organization('Impact Aid');
        $article = $this->article($organization);
        $firstUser = $this->user('first@test.com');
        $secondUser = $this->user('second@test.com');

        $this->impact($organization, $firstUser, $article);
        $this->impact($organization, $firstUser, $article);
        $this->impact($organization, $secondUser, $article);

        $this->getJson("/api/v1/organizations/{$organization->public_id}")
            ->assertOk()
            ->assertJsonPath('data.organization.supporters_count', 2);
    }

    public function test_categories_use_unique_non_empty_published_article_categories_only(): void
    {
        $organization = $this->organization('Category Aid');

        $this->article($organization, ['category' => 'Health']);
        $this->article($organization, ['category' => 'Health']);
        $this->article($organization, ['category' => 'Education']);
        $this->article($organization, ['category' => null]);
        $this->article($organization, ['category' => '']);
        $this->article($organization, ['category' => 'Draft Only', 'status' => 'draft']);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonPath('data.organizations.data.0.categories', ['Education', 'Health'])
            ->assertJsonPath('data.categories', ['Education', 'Health']);
    }

    public function test_index_category_filter_still_returns_matching_organizations_only(): void
    {
        $health = $this->organization('Health Aid');
        $education = $this->organization('Education Aid');

        $this->article($health, ['category' => 'Health']);
        $this->article($education, ['category' => 'Education']);

        $this->getJson('/api/v1/organizations?category=Health')
            ->assertOk()
            ->assertJsonCount(1, 'data.organizations.data')
            ->assertJsonPath('data.organizations.data.0.public_id', $health->public_id)
            ->assertJsonPath('data.categories', ['Education', 'Health']);
    }

    public function test_public_organizations_index_does_not_add_obvious_n_plus_one_queries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $organization = $this->organization('Aid '.$i);
            $article = $this->article($organization, ['category' => 'Category '.$i]);
            $this->impact($organization, $this->user('supporter'.$i.'@test.com'), $article);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/organizations?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.organizations.data');

        $this->assertLessThanOrEqual(7, $queries);
    }

    private function user(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role_id' => 1,
        ]);
    }

    private function organization(string $name, array $overrides = []): OrganizationProfile
    {
        $owner = User::factory()->create([
            'role_id' => 2,
            'full_name' => $name,
            'email' => Str::slug($name).'-owner@test.com',
        ]);

        return OrganizationProfile::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'organization_name' => $name,
            'tax_id' => fake()->unique()->numerify('#########'),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'payouts_enabled' => false,
            'charges_enabled' => false,
        ], $overrides));
    }

    private function article(OrganizationProfile $organization, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'organization_profile_id' => $organization->id,
            'title' => fake()->sentence(4),
            'slug' => (string) Str::uuid(),
            'excerpt' => 'A public article excerpt.',
            'content' => 'A public article body.',
            'category' => 'General',
            'status' => 'published',
            'published_at' => now(),
            'read_time' => 3,
            'total_reads' => 1,
        ], $overrides));
    }

    private function impact(OrganizationProfile $organization, User $user, Article $article): ImpactTransaction
    {
        return ImpactTransaction::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'organization_profile_id' => $organization->id,
            'article_id' => $article->id,
            'amount' => '0.07',
            'points_generated' => 10,
            'transaction_month' => now()->startOfMonth()->toDateString(),
        ]);
    }
}
