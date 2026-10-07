<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicArticlesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'subscriber', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'organization', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_guest_can_list_published_articles_without_protected_content(): void
    {
        $organization = $this->organization('Public Aid', [
            'metadata' => ['logo_url' => 'https://cdn.test/public-aid.png'],
        ]);
        $published = $this->article($organization, [
            'title' => 'Published Story',
            'category' => 'Health',
            'content' => 'Protected full body should not appear on listing.',
            'total_reads' => 9,
            'total_unique_reads' => 4,
        ]);
        $this->article($organization, ['status' => 'draft', 'title' => 'Draft Story']);
        $this->article($organization, ['status' => 'rejected', 'title' => 'Rejected Story']);
        $this->article($organization, ['status' => 'published', 'title' => 'Archived Story'])->delete();

        $this->getJson('/api/v1/articles?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $published->public_id)
            ->assertJsonPath('data.articles.data.0.title', 'Published Story')
            ->assertJsonPath('data.articles.data.0.excerpt', 'A safe article excerpt.')
            ->assertJsonPath('data.articles.data.0.category', 'Health')
            ->assertJsonPath('data.articles.data.0.organization.name', 'Public Aid')
            ->assertJsonPath('data.articles.data.0.organization.logo_url', 'https://cdn.test/public-aid.png')
            ->assertJsonPath('data.articles.data.0.total_reads', 9)
            ->assertJsonPath('data.articles.per_page', 1)
            ->assertJsonPath('data.categories', ['Health'])
            ->assertJsonMissingPath('data.articles.data.0.content')
            ->assertJsonMissingPath('data.articles.data.0.body')
            ->assertJsonMissingPath('data.articles.data.0.status')
            ->assertJsonMissingPath('data.articles.data.0.rejection_reason')
            ->assertJsonMissingPath('data.articles.data.0.organization.stripe_connect_account_id');
    }

    public function test_public_article_list_category_filter_returns_matching_published_articles_only(): void
    {
        $organization = $this->organization('Category Aid');
        $health = $this->article($organization, ['title' => 'Health Story', 'category' => 'Health']);
        $this->article($organization, ['title' => 'Education Story', 'category' => 'Education']);
        $this->article($organization, ['title' => 'Draft Health', 'category' => 'Health', 'status' => 'draft']);

        $this->getJson('/api/v1/articles?category=Health')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles.data')
            ->assertJsonPath('data.articles.data.0.public_id', $health->public_id)
            ->assertJsonPath('data.categories', ['Education', 'Health']);
    }

    public function test_guest_article_detail_returns_preview_paywall_without_side_effects(): void
    {
        $organization = $this->organization('Preview Aid', [
            'metadata' => [
                'logo_url' => 'https://cdn.test/preview.png',
                'private_note' => 'do not expose',
            ],
            'stripe_connect_account_id' => 'acct_private',
            'payouts_enabled' => true,
        ]);
        $article = $this->article($organization, [
            'content' => 'Full protected body for paid subscribers only.',
            'rejection_reason' => 'Private moderation note.',
            'metadata' => ['internal_editor_note' => 'hidden'],
        ]);

        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.public_id', $article->public_id)
            ->assertJsonPath('data.article.preview.excerpt', 'A safe article excerpt.')
            ->assertJsonPath('data.article.preview.has_dedicated_preview', true)
            ->assertJsonPath('data.article.organization.public_id', $organization->public_id)
            ->assertJsonPath('data.article.organization.name', 'Preview Aid')
            ->assertJsonPath('data.article.organization.logo_url', 'https://cdn.test/preview.png')
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonPath('data.access.requires_subscription', true)
            ->assertJsonMissingPath('data.article.content')
            ->assertJsonMissingPath('data.article.body')
            ->assertJsonMissingPath('data.article.metadata')
            ->assertJsonMissingPath('data.article.rejection_reason')
            ->assertJsonMissingPath('data.article.organization.private_note')
            ->assertJsonMissingPath('data.article.organization.stripe_connect_account_id');

        $this->assertDatabaseCount('article_reads', 0);
        $this->assertDatabaseCount('impact_transactions', 0);
    }

    public function test_authenticated_non_subscriber_and_inactive_subscription_remain_paywalled(): void
    {
        $article = $this->article($this->organization('Paywall Aid'));
        $nonSubscriber = $this->user('nosub@test.com');
        $inactiveSubscriber = $this->user('inactive@test.com');
        $this->subscription($inactiveSubscriber, ['status' => 'canceled', 'expires_at' => now()->addMonth()]);

        Sanctum::actingAs($nonSubscriber);
        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');

        Sanctum::actingAs($inactiveSubscriber);
        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');
    }

    public function test_active_subscriber_receives_full_content_and_access_is_user_specific(): void
    {
        $article = $this->article($this->organization('Subscriber Aid'), [
            'content' => 'Subscriber-only full article body.',
        ]);
        $subscriber = $this->user('subscriber@test.com');
        $otherUser = $this->user('other@test.com');
        $this->subscription($subscriber);

        Sanctum::actingAs($subscriber);
        $this->getJson("/api/v1/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', true)
            ->assertJsonPath('data.access.requires_subscription', false)
            ->assertJsonPath('data.article.content', 'Subscriber-only full article body.');

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/v1/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');
    }

    public function test_organization_and_admin_accounts_do_not_receive_subscriber_article_entitlement(): void
    {
        $article = $this->article($this->organization('Entitlement Aid'));
        $organizationUser = $this->user('org-account@test.com', ['role_id' => 2]);
        $admin = $this->user('admin@test.com', ['role_id' => 3]);
        $this->subscription($organizationUser);
        $this->subscription($admin);

        Sanctum::actingAs($organizationUser);
        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');
    }

    public function test_non_public_archived_and_missing_articles_return_safe_not_found(): void
    {
        $organization = $this->organization('Hidden Aid');
        $draft = $this->article($organization, ['status' => 'draft']);
        $rejected = $this->article($organization, ['status' => 'rejected']);
        $archived = $this->article($organization);
        $archived->delete();

        $this->getJson("/api/v1/articles/{$draft->public_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested article was not found.');

        $this->getJson("/api/v1/articles/{$rejected->public_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested article was not found.');

        $this->getJson("/api/v1/articles/{$archived->public_id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested article was not found.');

        $this->getJson('/api/v1/articles/not-a-real-article')
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested article was not found.');
    }

    public function test_optional_authentication_allows_guests_and_ignores_invalid_tokens_without_granting_access(): void
    {
        $article = $this->article($this->organization('Optional Auth Aid'));

        $this->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson("/api/v1/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');
    }

    public function test_public_article_index_avoids_obvious_n_plus_one_queries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->article($this->organization('Aid '.$i), ['category' => 'Category '.$i]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/articles?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.articles.data');

        $this->assertLessThanOrEqual(4, $queries);
    }

    public function test_existing_member_article_read_flow_still_works(): void
    {
        $article = $this->article($this->organization('Read Aid'));
        $subscriber = $this->user('reader@test.com');
        $this->subscription($subscriber);

        Sanctum::actingAs($subscriber);

        $this->getJson('/api/v1/member/articles?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id);

        $this->postJson("/api/v1/member/articles/{$article->public_id}/read", [
            'read_percent' => 100,
            'reading_seconds' => 180,
        ])
            ->assertCreated()
            ->assertJsonPath('data.read.counted_for_payout', true)
            ->assertJsonPath('data.impact_transaction.amount', '0.07');
    }

    private function user(string $email, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => $email,
            'role_id' => 1,
            'email_verified_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function organization(string $name, array $overrides = []): OrganizationProfile
    {
        $owner = $this->user(Str::slug($name).'-owner@test.com', [
            'role_id' => 2,
            'full_name' => $name,
        ]);

        return OrganizationProfile::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'organization_name' => $name,
            'tax_id' => fake()->unique()->numerify('#########'),
            'certificate_file' => 'organization-certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'payouts_enabled' => false,
            'charges_enabled' => false,
        ], $overrides));
    }

    private function article(OrganizationProfile $organization, array $overrides = []): Article
    {
        $title = $overrides['title'] ?? fake()->sentence(4);

        return Article::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'organization_profile_id' => $organization->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'excerpt' => 'A safe article excerpt.',
            'content' => 'Full protected article body.',
            'category' => 'General',
            'status' => 'published',
            'published_at' => now(),
            'read_time' => 3,
            'total_reads' => 0,
            'total_unique_reads' => 0,
            'total_reading_seconds' => 0,
            'total_points_generated' => 0,
        ], $overrides));
    }

    private function subscription(User $user, array $overrides = []): Subscription
    {
        return Subscription::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_'.Str::random(12),
            'stripe_subscription_id' => 'sub_'.Str::random(12),
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ], $overrides));
    }
}
