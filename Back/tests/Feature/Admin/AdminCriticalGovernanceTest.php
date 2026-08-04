<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Admin\Concerns\InteractsWithCriticalAdminData;
use Tests\TestCase;

class AdminCriticalGovernanceTest extends TestCase
{
    use InteractsWithCriticalAdminData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->prepareCriticalAdminTest();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_users_admin_suspends_reactivates_resets_mfa_and_revokes_tokens_with_audit_logs(): void
    {
        $admin = $this->createAdminWithRole('admin_users');
        $target = $this->createUserWithRole('subscriber', [
            'first_login_mfa_completed_at' => now(),
            'phone_mfa_verified_at' => now(),
            'mfa_enrolled_at' => now(),
        ]);
        $target->createToken('web');
        $target->createToken('mobile');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.user.account_status', 'suspended');
        $this->assertFalse($target->refresh()->is_active);

        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.user.account_status', 'active');

        $this->postJson("/api/v1/admin/users/{$target->public_id}/reset-mfa")
            ->assertOk()
            ->assertJsonPath('data.user.mfa_completed', false);
        $this->assertNull($target->refresh()->first_login_mfa_completed_at);
        $this->assertNotNull($target->email_verified_at);

        $this->deleteJson("/api/v1/admin/users/{$target->public_id}/tokens")
            ->assertOk()
            ->assertJsonPath('data.tokens_revoked', 2);
        $this->assertSame(0, $target->tokens()->count());

        foreach (['user.suspended', 'user.activated', 'user.mfa_reset', 'user.tokens_revoked'] as $action) {
            $this->assertDatabaseHas('admin_logs', [
                'admin_id' => $admin->id,
                'action' => $action,
                'entity_id' => $target->public_id,
            ]);
        }
    }

    public function test_user_management_self_last_super_admin_not_found_and_permission_rules(): void
    {
        $usersAdmin = $this->createAdminWithRole('admin_users');
        $settingsAdmin = $this->createAdminWithRole('admin_settings');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $superAdmin = $this->createAdminWithRole('super_admin', ['email' => 'last-super@admin.test']);

        Sanctum::actingAs($usersAdmin);
        $this->patchJson("/api/v1/admin/users/{$usersAdmin->public_id}/status", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot suspend your own active admin session.');

        $this->patchJson('/api/v1/admin/users/missing-user/status', ['is_active' => false])
            ->assertNotFound();

        Sanctum::actingAs($settingsAdmin);
        $this->patchJson("/api/v1/admin/users/{$usersAdmin->public_id}/status", ['is_active' => false])
            ->assertForbidden();

        Sanctum::actingAs($readonly);
        $this->postJson("/api/v1/admin/users/{$usersAdmin->public_id}/reset-mfa")
            ->assertForbidden();

        Sanctum::actingAs($superAdmin);
        $this->putJson("/api/v1/admin/admins/{$superAdmin->public_id}/roles", [
            'roles' => ['admin_readonly'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove the last active super admin.');
    }

    public function test_content_admin_moderates_articles_and_list_detail_contracts_are_preserved(): void
    {
        $contentAdmin = $this->createAdminWithRole('admin_content');
        $financeAdmin = $this->createAdminWithRole('admin_finance');
        $organization = $this->createOrganizationProfile();
        $pending = $this->createArticle($organization, [
            'title' => 'Pending Review Story',
            'content' => '<p>Complete pending body.</p>',
        ]);
        $draft = $this->createArticle($organization, [
            'title' => 'Draft Story',
            'slug' => 'draft-story-'.str()->random(6),
            'status' => 'draft',
        ]);

        Sanctum::actingAs($contentAdmin);

        $this->getJson('/api/v1/admin/articles?status=pending_review')
            ->assertOk()
            ->assertJsonPath('data.articles.data.0.public_id', $pending->public_id)
            ->assertJsonMissingPath('data.articles.data.0.body')
            ->assertJsonMissingPath('data.articles.data.0.content');

        $this->getJson("/api/v1/admin/articles/{$pending->public_id}")
            ->assertOk()
            ->assertJsonPath('data.article.body', '<p>Complete pending body.</p>');

        $this->postJson("/api/v1/admin/articles/{$pending->public_id}/approve")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'published');
        $this->assertNotNull($pending->refresh()->published_at);

        $this->postJson("/api/v1/admin/articles/{$pending->public_id}/feature")
            ->assertOk()
            ->assertJsonPath('data.article.featured', true);

        $this->deleteJson("/api/v1/admin/articles/{$pending->public_id}/feature")
            ->assertOk()
            ->assertJsonPath('data.article.featured', false);

        $this->postJson("/api/v1/admin/articles/{$pending->public_id}/archive")
            ->assertOk()
            ->assertJsonPath('data.article.status', 'archived');

        $this->postJson("/api/v1/admin/articles/{$draft->public_id}/feature")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only published articles can be featured.');

        $this->postJson("/api/v1/admin/articles/{$draft->public_id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/admin/articles/{$draft->public_id}/reject", [
            'reason' => 'Article needs stronger sourcing.',
        ])
            ->assertOk()
            ->assertJsonPath('data.article.status', 'rejected');

        Sanctum::actingAs($financeAdmin);
        $this->postJson("/api/v1/admin/articles/{$draft->public_id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('admin_logs', ['action' => 'article.approved', 'entity_id' => $pending->public_id]);
        $this->assertDatabaseHas('admin_logs', ['action' => 'article.rejected', 'entity_id' => $draft->public_id]);
    }

    public function test_settings_admin_updates_valid_settings_and_validation_prevents_partial_invalid_writes(): void
    {
        $settingsAdmin = $this->createAdminWithRole('admin_settings');
        $readonly = $this->createAdminWithRole('admin_readonly');

        Sanctum::actingAs($settingsAdmin);

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 40,
            'operations_percentage' => 30,
            'growth_percentage' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.nonprofit_percentage', 40);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'impact_split.nonprofit_percentage',
            'updated_by' => $settingsAdmin->id,
        ]);

        $this->getJson('/api/v1/admin/settings/impact_split')->assertOk();
        $this->assertTrue(Cache::has('platform_settings:impact_split'));

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 70,
            'operations_percentage' => 20,
            'growth_percentage' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total']);
        $this->assertDatabaseMissing('platform_settings', [
            'key' => 'impact_split.nonprofit_percentage',
            'value' => json_encode(70),
        ]);

        $this->putJson('/api/v1/admin/settings/subscription_plans', [
            'plans' => [[
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => '7.001',
                'stripe_price_id' => 'bad_price',
                'enabled' => true,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plans.0.display_price', 'plans.0.stripe_price_id']);

        $this->putJson('/api/v1/admin/settings/subscription_plans', [
            'plans' => [[
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => '7.00',
                'stripe_price_id' => 'price_valid123',
                'enabled' => true,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.plans.0.stripe_price_id', 'price_valid123');

        Sanctum::actingAs($readonly);
        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 33,
            'operations_percentage' => 33,
            'growth_percentage' => 34,
        ])->assertForbidden();

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $settingsAdmin->id,
            'action' => 'platform_settings.updated',
            'entity_type' => 'platform_settings',
        ]);
    }

    public function test_authorization_matrix_for_critical_admin_roles(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $content = $this->createAdminWithRole('admin_content');
        $users = $this->createAdminWithRole('admin_users');
        $settings = $this->createAdminWithRole('admin_settings');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $member = $this->createUserWithRole('subscriber');
        $payment = $this->createRefundablePayment($member);
        $organization = $this->createOrganizationProfile();
        $article = $this->createArticle($organization);
        $batch = $this->createPendingPayoutBatch($organization);

        Sanctum::actingAs($finance);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertOk();
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/approve")->assertForbidden();

        Sanctum::actingAs($content);
        $this->postJson("/api/v1/admin/articles/{$article->public_id}/approve")->assertOk();
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertForbidden();

        Sanctum::actingAs($users);
        $this->patchJson("/api/v1/admin/users/{$member->public_id}/status", ['is_active' => false])->assertOk();
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")->assertForbidden();

        Sanctum::actingAs($settings);
        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 34,
            'operations_percentage' => 33,
            'growth_percentage' => 33,
        ])->assertOk();
        $this->patchJson("/api/v1/admin/users/{$member->public_id}/status", ['is_active' => true])->assertForbidden();

        Sanctum::actingAs($readonly);
        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson('/api/v1/admin/articles')->assertOk();
        $this->getJson('/api/v1/admin/subscriptions')->assertOk();
        $this->getJson('/api/v1/admin/payouts')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->deleteJson("/api/v1/admin/users/{$member->public_id}/tokens")->assertForbidden();

        $finance->roles()->firstOrFail()->revokePermissionTo('payments.refund');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance->refresh());
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertForbidden();
    }
}
