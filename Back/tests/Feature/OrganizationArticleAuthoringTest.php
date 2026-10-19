<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationArticleAuthoringTest extends TestCase
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

    public function test_access_requires_approved_organization_account(): void
    {
        $this->getJson('/api/v1/organization/articles')->assertUnauthorized();

        Sanctum::actingAs($this->user('subscriber@test.com', ['role_id' => 1]));
        $this->getJson('/api/v1/organization/articles')
            ->assertForbidden()
            ->assertJsonPath('message', 'Organization access is required.');

        Sanctum::actingAs($this->user('admin@test.com', ['role_id' => 3]));
        $this->getJson('/api/v1/organization/articles')
            ->assertForbidden()
            ->assertJsonPath('message', 'Organization access is required.');

        $pending = $this->organization('Pending Aid', ['verification_status' => 'pending'], false);
        Sanctum::actingAs($pending->user);
        $this->postJson('/api/v1/organization/articles', $this->articlePayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Organization account is not active.');

        $approved = $this->organization('Approved Aid');
        Sanctum::actingAs($approved->user);
        $this->getJson('/api/v1/organization/articles')->assertOk();
    }

    public function test_organization_can_create_draft_and_privileged_fields_are_rejected(): void
    {
        $organization = $this->organization('Draft Aid');
        $otherOrganization = $this->organization('Other Aid');
        Sanctum::actingAs($organization->user);

        $this->postJson('/api/v1/organization/articles', array_merge($this->articlePayload(), [
            'organization_profile_id' => $otherOrganization->id,
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'featured_at' => now()->toIso8601String(),
            'total_reads' => 999,
            'metadata' => ['admin' => true],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'organization_profile_id',
                'status',
                'published_at',
                'featured_at',
                'total_reads',
                'metadata',
            ]);

        $response = $this->postJson('/api/v1/organization/articles', $this->articlePayload([
            'title' => 'Clean Water Story',
            'slug' => 'clean-water-story',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.article.title', 'Clean Water Story')
            ->assertJsonPath('data.article.slug', 'clean-water-story')
            ->assertJsonPath('data.article.status', 'draft')
            ->assertJsonPath('data.article.content', 'Full article body for review.')
            ->assertJsonPath('data.article.total_reads', 0)
            ->assertJsonMissingPath('data.article.organization_profile_id')
            ->assertJsonMissingPath('data.article.metadata');

        $this->assertDatabaseHas('articles', [
            'public_id' => $response->json('data.article.public_id'),
            'organization_profile_id' => $organization->id,
            'status' => 'draft',
            'published_at' => null,
            'total_reads' => 0,
        ]);
    }

    public function test_malformed_article_field_types_return_validation_errors(): void
    {
        $organization = $this->organization('Input Safety Aid');
        Sanctum::actingAs($organization->user);

        $this->postJson('/api/v1/organization/articles', [
            'title' => ['unexpected'],
            'slug' => ['unexpected'],
            'excerpt' => ['unexpected'],
            'content' => 'Body',
            'category' => ['unexpected'],
        ])->assertStatus(422)->assertJsonValidationErrors(['title', 'slug', 'excerpt', 'category']);
    }

    public function test_duplicate_slugs_are_made_unique_for_new_drafts(): void
    {
        $organization = $this->organization('Slug Aid');
        $this->article($organization, ['slug' => 'shared-story']);

        Sanctum::actingAs($organization->user);

        $this->postJson('/api/v1/organization/articles', $this->articlePayload([
            'title' => 'Shared Story',
            'slug' => 'shared-story',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.article.slug', 'shared-story-2');
    }

    public function test_archived_article_slug_remains_reserved(): void
    {
        $organization = $this->organization('Archived Slug Aid');
        $this->article($organization, ['slug' => 'shared-story'])->delete();
        Sanctum::actingAs($organization->user);

        $this->postJson('/api/v1/organization/articles', $this->articlePayload([
            'title' => 'Shared Story',
            'slug' => 'shared-story',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.article.slug', 'shared-story-2');
    }

    public function test_organization_lists_and_views_only_own_articles_with_pagination(): void
    {
        $organization = $this->organization('Owner Aid');
        $otherOrganization = $this->organization('Neighbor Aid');
        $own = $this->article($organization, ['title' => 'Own Draft', 'content' => 'Own full content.']);
        $this->article($otherOrganization, ['title' => 'Other Draft']);

        Sanctum::actingAs($organization->user);

        $this->getJson('/api/v1/organization/articles?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles.data')
            ->assertJsonPath('data.articles.data.0.public_id', $own->public_id)
            ->assertJsonPath('data.articles.meta.per_page', 1)
            ->assertJsonMissingPath('data.articles.data.0.content')
            ->assertJsonMissingPath('data.articles.data.0.metadata');

        $this->getJson("/api/v1/organization/articles/{$own->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.public_id', $own->public_id)
            ->assertJsonPath('data.article.content', 'Own full content.')
            ->assertJsonMissingPath('data.article.organization_profile_id')
            ->assertJsonMissingPath('data.article.metadata');
    }

    public function test_cross_organization_article_access_and_mutation_are_not_allowed(): void
    {
        $organization = $this->organization('First Aid');
        $otherOrganization = $this->organization('Second Aid');
        $otherArticle = $this->article($otherOrganization);

        Sanctum::actingAs($organization->user);

        $this->getJson("/api/v1/organization/articles/{$otherArticle->public_id}")->assertNotFound();
        $this->patchJson("/api/v1/organization/articles/{$otherArticle->public_id}", ['title' => 'Stolen'])->assertNotFound();
        $this->postJson("/api/v1/organization/articles/{$otherArticle->public_id}/submit")->assertNotFound();
    }

    public function test_organization_can_edit_draft_fields_but_not_privileged_fields(): void
    {
        $organization = $this->organization('Edit Aid');
        $article = $this->article($organization);
        Sanctum::actingAs($organization->user);

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", [
            'title' => 'Updated Title',
            'excerpt' => 'Updated excerpt',
            'content' => str_repeat('updated ', 205),
            'category' => 'Health',
            'slug' => 'updated-title',
        ])
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Updated Title')
            ->assertJsonPath('data.article.slug', 'updated-title')
            ->assertJsonPath('data.article.category', 'Health')
            ->assertJsonPath('data.article.read_time_minutes', 2);

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", [
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'total_unique_reads' => 12,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'published_at', 'total_unique_reads']);

        $this->assertSame('draft', $article->refresh()->status);
        $this->assertNull($article->published_at);
        $this->assertSame(0, (int) $article->total_unique_reads);
    }

    public function test_under_review_published_and_rejected_articles_cannot_be_edited_or_submitted_by_organization(): void
    {
        $organization = $this->organization('State Aid');
        $pending = $this->article($organization, ['status' => 'pending_review']);
        $published = $this->article($organization, ['status' => 'published', 'published_at' => now()]);
        $rejected = $this->article($organization, ['status' => 'rejected', 'rejection_reason' => 'Final rejection.']);

        Sanctum::actingAs($organization->user);

        foreach ([$pending, $published, $rejected] as $article) {
            $this->patchJson("/api/v1/organization/articles/{$article->public_id}", ['title' => 'Nope'])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Only draft articles can be edited.');

            $this->postJson("/api/v1/organization/articles/{$article->public_id}/submit")
                ->assertStatus(422)
                ->assertJsonPath('message', 'Only draft articles can be submitted for review.');
        }
    }

    public function test_valid_draft_can_be_submitted_and_admin_moderation_queue_sees_it(): void
    {
        $organization = $this->organization('Submit Aid');
        $admin = $this->user('admin-submit@test.com', ['role_id' => 3]);
        $article = $this->article($organization, ['status' => 'draft']);

        Sanctum::actingAs($organization->user);

        $this->postJson("/api/v1/organization/articles/{$article->public_id}/submit")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'pending_review')
            ->assertJsonMissingPath('data.article.content');

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'pending_review',
            'rejection_reason' => null,
            'published_at' => null,
        ]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $organization->user_id,
            'action' => 'article.submitted',
            'entity_type' => 'article',
            'entity_id' => $article->public_id,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/articles?status=pending_review')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $article->public_id);
    }

    public function test_incomplete_draft_submission_fails_using_existing_required_content_fields(): void
    {
        $organization = $this->organization('Incomplete Aid');
        $article = $this->article($organization, ['content' => '<p>   </p>']);

        Sanctum::actingAs($organization->user);

        $this->postJson("/api/v1/organization/articles/{$article->public_id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please add article content before submitting this article.');

        $this->assertSame('draft', $article->refresh()->status);
    }

    public function test_request_changes_flow_allows_edit_and_resubmission(): void
    {
        $organization = $this->organization('Changes Aid');
        $admin = $this->user('admin-changes@test.com', ['role_id' => 3]);
        $article = $this->article($organization, ['status' => 'pending_review']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/request-changes", [
            'reason' => 'Please add stronger source information before publication.',
        ])
            ->assertOk()
            ->assertJsonPath('data.article.status', 'draft');

        Sanctum::actingAs($organization->user);
        $this->getJson("/api/v1/organization/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'draft')
            ->assertJsonPath('data.article.review_feedback', 'Please add stronger source information before publication.');

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", [
            'content' => 'Updated body with stronger sourcing.',
        ])->assertOk();

        $this->postJson("/api/v1/organization/articles/{$article->public_id}/submit")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'pending_review');

        $this->assertNull($article->refresh()->rejection_reason);
    }

    public function test_public_t01_visibility_and_paywall_are_preserved(): void
    {
        $organization = $this->organization('Public Safety Aid');
        $draft = $this->article($organization, ['title' => 'Draft Hidden', 'status' => 'draft']);
        $pending = $this->article($organization, ['title' => 'Pending Hidden', 'status' => 'pending_review']);
        $rejected = $this->article($organization, ['title' => 'Rejected Hidden', 'status' => 'rejected']);
        $published = $this->article($organization, [
            'title' => 'Published Visible',
            'status' => 'published',
            'published_at' => now(),
            'content' => 'Protected published body.',
        ]);
        $subscriber = $this->user('paid-reader@test.com', ['role_id' => 1]);
        $this->subscription($subscriber);

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data.articles.data')
            ->assertJsonPath('data.articles.data.0.public_id', $published->public_id);

        foreach ([$draft, $pending, $rejected] as $article) {
            $this->getJson("/api/v1/articles/{$article->public_id}")->assertNotFound();
        }

        $this->getJson("/api/v1/articles/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', false)
            ->assertJsonMissingPath('data.article.content');

        Sanctum::actingAs($subscriber);
        $this->getJson("/api/v1/articles/{$published->public_id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_read_full', true)
            ->assertJsonPath('data.article.content', 'Protected published body.');
    }

    public function test_authoring_actions_do_not_create_reads_impact_or_change_counters(): void
    {
        $organization = $this->organization('No Impact Aid');
        Sanctum::actingAs($organization->user);

        $create = $this->postJson('/api/v1/organization/articles', $this->articlePayload())
            ->assertCreated();

        $publicId = $create->json('data.article.public_id');

        $this->patchJson("/api/v1/organization/articles/{$publicId}", ['content' => 'Edited content.'])->assertOk();
        $this->postJson("/api/v1/organization/articles/{$publicId}/submit")->assertOk();

        $this->assertDatabaseCount('article_reads', 0);
        $this->assertDatabaseCount('impact_transactions', 0);
        $this->assertSame(0, (int) Article::where('public_id', $publicId)->value('total_reads'));
        $this->assertSame(0, (int) Article::where('public_id', $publicId)->value('total_points_generated'));
    }

    public function test_organization_article_index_avoids_obvious_n_plus_one_queries(): void
    {
        $organization = $this->organization('Performance Aid');

        for ($i = 0; $i < 5; $i++) {
            $this->article($organization, ['title' => 'Story '.$i]);
        }

        Sanctum::actingAs($organization->user);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/organization/articles?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.articles.data');

        $this->assertLessThanOrEqual(4, $queries);
    }

    private function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Community Impact Story',
            'excerpt' => 'A short author-provided excerpt.',
            'content' => 'Full article body for review.',
            'category' => 'Community',
        ], $overrides);
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

    private function organization(string $name, array $overrides = [], bool $active = true): OrganizationProfile
    {
        $owner = $this->user(Str::slug($name).'-owner@test.com', [
            'role_id' => 2,
            'full_name' => $name,
            'is_active' => $active,
            'email_verified_at' => now(),
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
            'status' => 'draft',
            'published_at' => null,
            'read_time' => 3,
            'total_reads' => 0,
            'total_unique_reads' => 0,
            'total_reading_seconds' => 0,
            'total_points_generated' => 0,
        ], $overrides));
    }

    private function subscription(User $user): void
    {
        DB::table('subscriptions')->insert([
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
