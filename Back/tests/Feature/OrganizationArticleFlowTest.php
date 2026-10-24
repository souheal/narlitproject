<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Notification;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The full article lifecycle across the three roles:
 * organization submits → admin moderates → members read it.
 */
class OrganizationArticleFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_article_reaches_admin_queue_and_members_after_approval(): void
    {
        $org = $this->organization();
        $admin = $this->admin();
        $member = $this->member();

        Sanctum::actingAs($org->user);
        $publicId = $this->postJson('/api/v1/organization/articles', [
            'title' => 'Clean water for 400 families',
            'excerpt' => 'How a single well changed a village.',
            'body' => str_repeat('A long story about water. ', 40),
            'category' => 'Environment',
            'read_time_minutes' => 4,
            'status' => 'pending_review',
        ])->assertCreated()
            ->assertJsonPath('data.article.status', 'pending_review')
            ->json('data.article.public_id');

        // Not visible to readers while under review.
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/articles/{$publicId}")->assertNotFound();
        $this->assertNotContains($publicId, $this->memberFeedIds());

        // Shows up in the admin's pending queue.
        Sanctum::actingAs($admin);
        $queue = $this->getJson('/api/v1/admin/articles?status=pending_review')->assertOk();
        $this->assertContains($publicId, collect($queue->json('data.articles.data'))->pluck('public_id')->all());

        $this->postJson("/api/v1/admin/articles/{$publicId}/approve")->assertOk()
            ->assertJsonPath('data.article.status', 'published');

        // Now members see it, both in the feed and on the article page.
        Sanctum::actingAs($member);
        $this->assertContains($publicId, $this->memberFeedIds());
        $this->getJson("/api/v1/articles/{$publicId}")->assertOk();

        // And the organization was told.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $org->user_id,
            'type' => 'article.published',
            'action_url' => "/organization/articles/{$publicId}",
        ]);
    }

    public function test_changes_requested_article_can_be_edited_and_resubmitted(): void
    {
        $org = $this->organization();
        $admin = $this->admin();
        $article = $this->article($org, ['status' => 'pending_review']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/request-changes", [
            'reason' => 'Please cite the source of the survey numbers.',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $org->user_id,
            'type' => 'article.changes_requested',
        ]);

        Sanctum::actingAs($org->user);
        $this->getJson("/api/v1/organization/articles/{$article->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'draft')
            ->assertJsonPath('data.article.can_edit', true)
            ->assertJsonPath('data.article.change_request.reason', 'Please cite the source of the survey numbers.');

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", [
            'title' => 'Revised title',
            'body' => str_repeat('Now with sources. ', 40),
            'status' => 'pending_review',
        ])->assertOk()
            ->assertJsonPath('message', 'Article submitted for review.')
            ->assertJsonPath('data.article.status', 'pending_review')
            ->assertJsonPath('data.article.title', 'Revised title')
            ->assertJsonPath('data.article.resubmission_count', 1);

        $this->assertNull($article->refresh()->rejection_reason);

        Sanctum::actingAs($admin);
        $queue = $this->getJson('/api/v1/admin/articles?status=pending_review')->assertOk();
        $this->assertContains($article->public_id, collect($queue->json('data.articles.data'))->pluck('public_id')->all());
    }

    public function test_rejection_notifies_the_organization_and_allows_resubmission(): void
    {
        $org = $this->organization();
        $article = $this->article($org, ['status' => 'pending_review']);

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/reject", [
            'reason' => 'This reads as an advertisement, not a story.',
        ])->assertOk();

        $notification = Notification::query()->where('user_id', $org->user_id)->first();
        $this->assertSame('article.rejected', $notification->type);
        $this->assertStringContainsString('advertisement', $notification->body);

        Sanctum::actingAs($org->user);
        $this->getJson("/api/v1/organization/articles/{$article->public_id}")
            ->assertJsonPath('data.article.rejection_reason', 'This reads as an advertisement, not a story.')
            ->assertJsonPath('data.article.can_edit', true);

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", ['status' => 'pending_review'])
            ->assertOk()
            ->assertJsonPath('data.article.status', 'pending_review');
    }

    public function test_editing_a_live_article_sends_it_back_to_review_while_approval_is_required(): void
    {
        $org = $this->organization();
        $member = $this->member();
        $published = $this->article($org, ['status' => 'published', 'published_at' => now()->subDay()]);
        $originalPublishedAt = $published->published_at->toIso8601String();

        Sanctum::actingAs($org->user);
        $this->postJson("/api/v1/organization/articles/{$published->public_id}", [
            '_method' => 'PATCH',
            'title' => 'Corrected headline',
            'status' => 'publish',
        ])->assertOk()
            ->assertJsonPath('message', 'Article submitted for review.')
            ->assertJsonPath('data.article.status', 'pending_review');

        // Hidden from readers until an admin approves the new version…
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/articles/{$published->public_id}")->assertNotFound();

        // …and approval keeps the original publish date.
        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/admin/articles/{$published->public_id}/approve")->assertOk();
        $this->assertSame($originalPublishedAt, $published->refresh()->published_at->toIso8601String());
    }

    public function test_publish_goes_live_immediately_when_admin_turned_approval_off(): void
    {
        $this->setApprovalRequired(false);
        $org = $this->organization();

        Sanctum::actingAs($org->user);
        $created = $this->postJson('/api/v1/organization/articles', [
            'title' => 'Straight to readers',
            'body' => str_repeat('Story text. ', 30),
            'status' => 'publish',
        ])->assertCreated()
            ->assertJsonPath('message', 'Article published.')
            ->assertJsonPath('data.article.status', 'published')
            ->assertJsonPath('data.article.approval_required', false);

        $publicId = $created->json('data.article.public_id');
        $this->assertNotNull(Article::query()->where('public_id', $publicId)->value('published_at'));

        // Editing a live article keeps it live when no approval is needed.
        $this->patchJson("/api/v1/organization/articles/{$publicId}", ['title' => 'Edited', 'status' => 'publish'])
            ->assertOk()
            ->assertJsonPath('data.article.status', 'published');

        Sanctum::actingAs($this->member());
        $this->getJson("/api/v1/articles/{$publicId}")->assertOk()->assertJsonPath('data.article.title', 'Edited');
    }

    public function test_org_can_withdraw_a_pending_article_or_unpublish_a_live_one(): void
    {
        $org = $this->organization();
        $pending = $this->article($org, ['status' => 'pending_review']);
        $live = $this->article($org, ['status' => 'published', 'published_at' => now(), 'featured_at' => now()]);

        Sanctum::actingAs($org->user);

        $this->patchJson("/api/v1/organization/articles/{$pending->public_id}", ['status' => 'draft'])
            ->assertOk()->assertJsonPath('data.article.status', 'draft');

        $this->patchJson("/api/v1/organization/articles/{$live->public_id}", ['status' => 'draft'])
            ->assertOk()->assertJsonPath('data.article.status', 'draft');
        $this->assertNull($live->refresh()->featured_at);
    }

    public function test_article_with_cover_and_gallery_images(): void
    {
        Storage::fake('public');
        $org = $this->organization();

        Sanctum::actingAs($org->user);
        $response = $this->post('/api/v1/organization/articles', [
            'title' => 'Photo story',
            'body' => str_repeat('Words and pictures. ', 30),
            'status' => 'publish',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $publicId = $response->json('data.article.public_id');
        $this->assertNotNull($response->json('data.article.cover_image_url'));
        $this->assertCount(2, $response->json('data.article.images'));

        $article = Article::query()->where('public_id', $publicId)->first();
        $coverPath = $article->metadata['cover_image_path'];
        [$first, $second] = $article->metadata['gallery'];
        Storage::disk('public')->assertExists([$coverPath, $first['path'], $second['path']]);

        // Edit: replace the cover, drop one gallery photo, add another — multipart PATCH via _method.
        $this->post("/api/v1/organization/articles/{$publicId}", [
            '_method' => 'PATCH',
            'cover_image' => UploadedFile::fake()->image('new-cover.jpg'),
            'remove_images' => [$first['id']],
            'images' => [UploadedFile::fake()->image('three.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonCount(2, 'data.article.images');

        Storage::disk('public')->assertMissing([$coverPath, $first['path']]);
        Storage::disk('public')->assertExists($second['path']);

        // Remove the cover entirely.
        $this->patchJson("/api/v1/organization/articles/{$publicId}", ['remove_cover' => true])
            ->assertOk()
            ->assertJsonPath('data.article.cover_image_url', null);

        // Admins reviewing it see the photos.
        Sanctum::actingAs($this->admin());
        $this->getJson("/api/v1/admin/articles/{$publicId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.article.images');
    }

    public function test_gallery_is_capped_and_only_accepts_images(): void
    {
        Storage::fake('public');
        $org = $this->organization();
        $article = $this->article($org);

        Sanctum::actingAs($org->user);

        $this->post("/api/v1/organization/articles/{$article->public_id}", [
            '_method' => 'PATCH',
            'images' => array_map(fn (int $i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 9)),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post("/api/v1/organization/articles/{$article->public_id}", [
            '_method' => 'PATCH',
            'cover_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonValidationErrors('cover_image');
    }

    public function test_org_can_delete_an_article_and_admin_can_still_restore_it(): void
    {
        $org = $this->organization();
        $live = $this->article($org, ['status' => 'published', 'published_at' => now()]);

        Sanctum::actingAs($org->user);
        $this->deleteJson("/api/v1/organization/articles/{$live->public_id}")
            ->assertOk()
            ->assertJsonPath('data.deleted_public_id', $live->public_id);

        $this->getJson("/api/v1/organization/articles/{$live->public_id}")->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/v1/organization/articles')->json('data.articles.total'));

        Sanctum::actingAs($this->member());
        $this->getJson("/api/v1/articles/{$live->public_id}")->assertNotFound();

        Sanctum::actingAs($this->admin());
        $archived = $this->getJson('/api/v1/admin/articles?status=archived')->assertOk();
        $this->assertContains($live->public_id, collect($archived->json('data.articles.data'))->pluck('public_id')->all());
        $this->postJson("/api/v1/admin/articles/{$live->public_id}/restore")->assertOk();
        $this->assertSame('published', $live->refresh()->status);
    }

    public function test_org_cannot_delete_another_organizations_article(): void
    {
        $mine = $this->organization('mine@test.com');
        $article = $this->article($this->organization('theirs@test.com'));

        Sanctum::actingAs($mine->user);
        $this->deleteJson("/api/v1/organization/articles/{$article->public_id}")->assertNotFound();
        $this->assertFalse($article->refresh()->trashed());
    }

    public function test_organization_cannot_see_another_organizations_article(): void
    {
        $mine = $this->organization('mine@test.com');
        $theirs = $this->organization('theirs@test.com');
        $article = $this->article($theirs, ['status' => 'draft']);

        Sanctum::actingAs($mine->user);

        $this->getJson("/api/v1/organization/articles/{$article->public_id}")->assertNotFound();
        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Article Title', $article->refresh()->title);
    }

    public function test_edit_rejects_blank_title_or_content(): void
    {
        $org = $this->organization();
        $article = $this->article($org, ['status' => 'draft']);

        Sanctum::actingAs($org->user);

        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", ['body' => '   '])
            ->assertStatus(422);
        $this->patchJson("/api/v1/organization/articles/{$article->public_id}", ['title' => ''])
            ->assertStatus(422);
    }

    private function setApprovalRequired(bool $required): void
    {
        DB::table('platform_settings')->insert([
            'key' => 'content.article_approval_required',
            'value' => json_encode(['value' => $required]),
            'group' => 'content',
            'type' => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();
    }

    /** @return list<string> */
    private function memberFeedIds(): array
    {
        $response = $this->getJson('/api/v1/member/articles?per_page=50')->assertOk();

        return collect($response->json('data.articles.data'))->pluck('public_id')->all();
    }

    private function organization(string $email = 'org@test.com'): OrganizationProfile
    {
        $user = $this->user('organization', $email);

        return OrganizationProfile::query()->create([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'organization_name' => 'Org '.$email,
            'tax_id' => (string) random_int(100000000, 999999999),
            'certificate_file' => 'certificates/test.pdf',
            'irs_verified' => true,
            'verification_status' => 'approved',
            'reviewed_at' => now(),
        ])->load('user');
    }

    private function admin(): User
    {
        $admin = $this->user('admin', 'admin@test.com');
        $admin->forceFill(['first_login_mfa_completed_at' => now()])->save();

        return $admin;
    }

    private function member(): User
    {
        $member = $this->user('member', 'member@test.com');

        DB::table('subscriptions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $member->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'plan' => 'monthly',
            'amount' => 10,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $member;
    }

    private function user(string $role, string $email): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $role, 'created_at' => now(), 'updated_at' => now()]);

        $user = new User();
        $user->forceFill([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => ucfirst($role).' User',
            'username' => str()->slug($email),
            'email' => $email,
            'password' => bcrypt('password'),
            'gender' => 'other',
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function article(OrganizationProfile $organization, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'public_id' => (string) str()->uuid(),
            'organization_profile_id' => $organization->id,
            'title' => 'Article Title',
            'slug' => 'article-'.str()->random(8),
            'excerpt' => 'Short excerpt',
            'content' => str_repeat('Full article content. ', 20),
            'category' => 'General',
            'status' => 'draft',
            'read_time' => 4,
        ], $overrides));
    }
}
