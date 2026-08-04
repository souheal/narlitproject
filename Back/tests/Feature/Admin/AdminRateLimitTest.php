<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\InteractsWithCriticalAdminData;
use Tests\TestCase;

class AdminRateLimitTest extends TestCase
{
    use InteractsWithCriticalAdminData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->prepareCriticalAdminTest();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_read_request_below_and_above_limit(): void
    {
        config()->set('security.admin_rate_limits.read_per_minute', 1);
        $admin = $this->createAdminWithRole('admin_readonly');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/v1/admin/users')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_destructive_request_limit_blocks_eleventh_default_request(): void
    {
        config()->set('security.admin_rate_limits.destructive_per_minute', 10);
        $admin = $this->createAdminWithRole('admin_users');
        $targets = collect(range(1, 11))
            ->map(fn () => $this->createUserWithRole('subscriber'));

        Sanctum::actingAs($admin);

        $targets->take(10)->each(function ($target): void {
            $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
                'is_active' => false,
            ])->assertOk();
        });

        $lastTarget = $targets->last();
        $this->patchJson("/api/v1/admin/users/{$lastTarget->public_id}/status", [
            'is_active' => false,
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertTrue($lastTarget->refresh()->is_active);
    }

    public function test_audit_export_and_analytics_have_dedicated_limits(): void
    {
        config()->set('security.admin_rate_limits.audit_export_per_hour', 1);
        config()->set('security.admin_rate_limits.analytics_per_minute', 1);
        $admin = $this->createAdminWithRole('super_admin');

        Sanctum::actingAs($admin);

        $this->get('/api/v1/admin/audit/export')->assertOk();
        $this->get('/api/v1/admin/audit/export')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->getJson('/api/v1/admin/analytics/overview')->assertOk();
        $this->getJson('/api/v1/admin/analytics/overview')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_settings_update_uses_dedicated_limit(): void
    {
        config()->set('security.admin_rate_limits.settings_update_per_minute', 1);
        $admin = $this->createAdminWithRole('admin_settings');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 40,
            'operations_percentage' => 30,
            'growth_percentage' => 30,
        ])->assertOk();

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 34,
            'operations_percentage' => 33,
            'growth_percentage' => 33,
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertDatabaseMissing('platform_settings', [
            'key' => 'impact_split.nonprofit_percentage',
            'value' => json_encode(34),
        ]);
    }

    public function test_different_admins_have_separate_read_buckets(): void
    {
        config()->set('security.admin_rate_limits.read_per_minute', 1);
        $firstAdmin = $this->createAdminWithRole('admin_readonly');
        $secondAdmin = $this->createAdminWithRole('admin_readonly');

        Sanctum::actingAs($firstAdmin);
        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson('/api/v1/admin/users')->assertTooManyRequests();

        Sanctum::actingAs($secondAdmin);
        $this->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_destructive_bucket_is_shared_across_destructive_routes_and_not_consumed_by_gets(): void
    {
        config()->set('security.admin_rate_limits.read_per_minute', 10);
        config()->set('security.admin_rate_limits.destructive_per_minute', 2);
        $admin = $this->createAdminWithRole('admin_users');
        $target = $this->createUserWithRole('subscriber', [
            'first_login_mfa_completed_at' => now(),
            'phone_mfa_verified_at' => now(),
            'mfa_enrolled_at' => now(),
        ]);
        $target->createToken('web');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson("/api/v1/admin/users/{$target->public_id}")->assertOk();

        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
            'is_active' => false,
        ])->assertOk();

        $this->postJson("/api/v1/admin/users/{$target->public_id}/reset-mfa")->assertOk();

        $this->deleteJson("/api/v1/admin/users/{$target->public_id}/tokens")
            ->assertTooManyRequests();

        $this->assertSame(1, $target->tokens()->count());
    }

    public function test_authentication_permission_and_readonly_fail_before_throttle_side_effects(): void
    {
        config()->set('security.admin_rate_limits.destructive_per_minute', 1);
        $target = $this->createUserWithRole('subscriber');
        $normalUser = $this->createUserWithRole('subscriber', [
            'email' => 'normal-rate-limit@test.local',
        ]);
        $contentAdmin = $this->createAdminWithRole('admin_content');
        $readonly = $this->createAdminWithRole('admin_readonly');

        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
            'is_active' => false,
        ])->assertUnauthorized();

        Sanctum::actingAs($normalUser);
        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
            'is_active' => false,
        ])->assertForbidden();

        Sanctum::actingAs($contentAdmin);
        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
            'is_active' => false,
        ])->assertForbidden();

        Sanctum::actingAs($readonly);
        $this->patchJson("/api/v1/admin/users/{$target->public_id}/status", [
            'is_active' => false,
        ])->assertForbidden();

        $this->assertTrue($target->refresh()->is_active);
    }

    public function test_throttled_financial_request_does_not_create_or_corrupt_idempotency_records(): void
    {
        config()->set('security.admin_rate_limits.destructive_per_minute', 1);
        $admin = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $payment = $this->createRefundablePayment($member);

        Sanctum::actingAs($admin);

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Customer reported a duplicate charge.',
        ])->assertOk();

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payments/{$payment->public_id}/refund", [
            'reason' => 'Second click should be throttled before idempotency.',
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertSame(1, DB::table('admin_logs')->where('action', 'payment.refunded')->count());
        $this->assertSame('refunded', $payment->refresh()->status);
    }
}
