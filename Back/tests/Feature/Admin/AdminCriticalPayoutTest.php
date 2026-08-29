<?php

namespace Tests\Feature\Admin;

use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\InteractsWithCriticalAdminData;
use Tests\TestCase;

class AdminCriticalPayoutTest extends TestCase
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

    public function test_finance_admin_generates_payout_batch_with_exact_totals_and_single_audit_entry(): void
    {
        $admin = $this->createAdminWithRole('admin_finance');
        $member = $this->createUserWithRole('subscriber');
        $organization = $this->createOrganizationProfile();
        $article = $this->createArticle($organization, ['status' => 'published', 'published_at' => now()->subDays(3)]);
        $subscription = $this->createActiveSubscription($member);
        $this->createRefundablePayment($member, $subscription, [
            'amount' => '7.00',
            'net_amount' => '6.70',
            'paid_at' => now()->subDays(2),
        ]);
        DB::table('article_reads')->insert([
            'article_id' => $article->id,
            'user_id' => $member->id,
            'read_percent' => 100,
            'reading_seconds' => 120,
            'points_earned' => 10,
            'counted_for_payout' => true,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);
        $key = (string) str()->uuid();

        $response = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payout_batch.batch.total_pool', '2.21')
            ->assertJsonPath('data.payout_batch.batch.total_distributed', '2.21')
            ->assertJsonPath('data.payout_batch.items.0.payout_amount', '2.21');

        $publicId = $response->json('data.payout_batch.batch.public_id');
        $createdBatch = PayoutBatch::query()->where('public_id', $publicId)->firstOrFail();
        $this->assertSame('2026-07-01', $createdBatch->batch_month->toDateString());
        $this->assertSame('pending', $createdBatch->status);
        $this->assertDatabaseCount('payout_items', 1);
        $this->assertSame(1, $this->auditCount('payout_batch.generated', $publicId));

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
        ])
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertDatabaseCount('payout_batches', 1);
        $this->assertSame(1, $this->auditCount('payout_batch.generated', $publicId));
    }

    public function test_payout_generation_validation_duplicates_and_permissions_are_enforced(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $content = $this->createAdminWithRole('admin_content');

        Sanctum::actingAs($finance);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payouts/generate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026/07',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        PayoutBatch::query()->create([
            'public_id' => (string) str()->uuid(),
            'batch_month' => '2026-07-01',
            'total_pool' => '0.00',
            'total_distributed' => '0.00',
            'total_organizations' => 0,
            'status' => 'pending',
        ]);

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-07',
        ])->assertStatus(409);
        $this->assertSame(1, $this->auditCount('payout_batch.generation_failed', '2026-07-01'));

        Sanctum::actingAs($readonly);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-08',
        ])->assertForbidden();

        Sanctum::actingAs($content);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson('/api/v1/admin/payouts/generate', [
            'month' => '2026-08',
        ])->assertForbidden();
    }

    public function test_finance_admin_executes_pending_batch_once_and_cannot_reexecute_completed_batch(): void
    {
        $admin = $this->createAdminWithRole('admin_finance');
        $organization = $this->createOrganizationProfile();
        $batch = $this->createPendingPayoutBatch($organization);
        $key = (string) str()->uuid();

        Sanctum::actingAs($admin);

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.status', 'completed');

        $item = PayoutItem::query()->firstOrFail();
        $this->assertSame('completed', $item->transfer_status);
        $this->assertSame('fake_transfer_'.$item->id, $item->stripe_transfer_id);
        $this->assertSame(1, $this->auditCount('payout_batch.execution_queued', $batch->public_id));

        $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertOk()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only pending or failed payout batches can be executed.');
        $this->assertSame(1, PayoutItem::query()->whereNotNull('stripe_transfer_id')->count());
        $this->assertSame(1, $this->auditCount('payout_batch.execution_failed', $batch->public_id));
    }

    public function test_payout_execution_handles_missing_connect_retry_and_permissions(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $member = $this->createUserWithRole('subscriber');
        $missingConnect = $this->createOrganizationProfile([
            'stripe_connect_account_id' => null,
            'payouts_enabled' => false,
        ]);
        $batch = $this->createPendingPayoutBatch($missingConnect);

        Sanctum::actingAs($readonly);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertForbidden();

        Sanctum::actingAs($member);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertForbidden();

        Sanctum::actingAs($finance);
        $this->withHeader('Idempotency-Key', (string) str()->uuid())->postJson("/api/v1/admin/payouts/{$batch->public_id}/execute")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.status', 'failed');

        $item = PayoutItem::query()->firstOrFail();
        $this->assertSame('failed', $item->transfer_status);
        $this->assertNull($item->stripe_transfer_id);

        $missingConnect->forceFill([
            'stripe_connect_account_id' => 'acct_retry',
            'payouts_enabled' => true,
        ])->save();

        $this->postJson("/api/v1/admin/payouts/items/{$item->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.payout_item.transfer_status', 'completed');

        $this->assertSame('completed', $item->refresh()->transfer_status);
        $this->assertSame('completed', $batch->refresh()->status);
    }

    public function test_readonly_payout_view_cannot_see_sensitive_operational_metadata(): void
    {
        $finance = $this->createAdminWithRole('admin_finance');
        $readonly = $this->createAdminWithRole('admin_readonly');
        $organization = $this->createOrganizationProfile([
            'stripe_connect_account_id' => 'acct_sensitive',
        ]);
        $batch = $this->createPendingPayoutBatch($organization);
        $item = $batch->items()->firstOrFail();

        $item->forceFill([
            'stripe_transfer_id' => 'tr_sensitive',
            'transfer_status' => 'failed',
            'metadata' => [
                'failure_reason' => 'Processor returned sensitive provider detail.',
                'engagement_share' => '1.0000',
                'processor_response' => 'provider-internal-value',
            ],
        ])->save();

        Sanctum::actingAs($readonly);

        $this->getJson("/api/v1/admin/payouts/{$batch->public_id}")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.items.0.organization.stripe_connect_account_id', null)
            ->assertJsonPath('data.payout_batch.items.0.stripe_transfer_id', null)
            ->assertJsonPath('data.payout_batch.items.0.failure_reason', null)
            ->assertJsonPath('data.payout_batch.items.0.metadata', null);

        Sanctum::actingAs($finance);

        $this->getJson("/api/v1/admin/payouts/{$batch->public_id}")
            ->assertOk()
            ->assertJsonPath('data.payout_batch.items.0.organization.stripe_connect_account_id', 'acct_sensitive')
            ->assertJsonPath('data.payout_batch.items.0.stripe_transfer_id', 'tr_sensitive')
            ->assertJsonPath('data.payout_batch.items.0.failure_reason', 'Processor returned sensitive provider detail.')
            ->assertJsonPath('data.payout_batch.items.0.metadata.processor_response', 'provider-internal-value');
    }

    protected function auditCount(string $action, string $entityId): int
    {
        return (int) DB::table('admin_logs')
            ->where('action', $action)
            ->where('entity_id', $entityId)
            ->count();
    }
}
