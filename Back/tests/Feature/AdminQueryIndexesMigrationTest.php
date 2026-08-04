<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminQueryIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_query_indexes_are_created_on_the_test_schema(): void
    {
        $this->assertIndexExists('admin_logs', 'admin_logs_admin_created_idx');
        $this->assertIndexExists('admin_logs', 'admin_logs_action_created_idx');
        $this->assertIndexExists('admin_logs', 'admin_logs_entity_created_idx');

        $this->assertIndexExists('articles', 'articles_status_created_idx');
        $this->assertIndexExists('articles', 'articles_org_status_idx');

        $this->assertIndexExists('subscriptions', 'subscriptions_canceled_at_idx');
        $this->assertIndexExists('subscriptions', 'subscriptions_started_at_idx');

        $this->assertIndexExists('payout_items', 'payout_items_batch_transfer_status_idx');

        $this->assertIndexExists('users', 'users_active_created_idx');
        $this->assertIndexExists('users', 'users_last_login_idx');
    }

    protected function assertIndexExists(string $table, string $indexName): void
    {
        $indexes = collect(DB::select("PRAGMA index_list('{$table}')"))
            ->pluck('name')
            ->all();

        $this->assertContains($indexName, $indexes);
    }
}
