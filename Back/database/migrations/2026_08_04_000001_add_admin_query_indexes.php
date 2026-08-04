<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->createIndex('admin_logs', 'admin_logs_admin_created_idx', ['admin_id', 'created_at']);
        $this->createIndex('admin_logs', 'admin_logs_action_created_idx', ['action', 'created_at']);
        $this->createIndex('admin_logs', 'admin_logs_entity_created_idx', ['entity_type', 'created_at']);

        $this->createIndex('articles', 'articles_status_created_idx', ['status', 'created_at']);
        $this->createIndex('articles', 'articles_org_status_idx', ['organization_profile_id', 'status']);

        $this->createIndex('subscriptions', 'subscriptions_canceled_at_idx', ['canceled_at']);
        $this->createIndex('subscriptions', 'subscriptions_started_at_idx', ['started_at']);

        $this->createIndex('payout_items', 'payout_items_batch_transfer_status_idx', ['payout_batch_id', 'transfer_status']);

        $this->createIndex('users', 'users_active_created_idx', ['is_active', 'created_at']);
        $this->createIndex('users', 'users_last_login_idx', ['last_login_at']);
    }

    public function down(): void
    {
        $this->dropIndex('admin_logs', 'admin_logs_admin_created_idx');
        $this->dropIndex('admin_logs', 'admin_logs_action_created_idx');
        $this->dropIndex('admin_logs', 'admin_logs_entity_created_idx');

        $this->dropIndex('articles', 'articles_status_created_idx');
        $this->dropIndex('articles', 'articles_org_status_idx');

        $this->dropIndex('subscriptions', 'subscriptions_canceled_at_idx');
        $this->dropIndex('subscriptions', 'subscriptions_started_at_idx');

        $this->dropIndex('payout_items', 'payout_items_batch_transfer_status_idx');

        $this->dropIndex('users', 'users_active_created_idx');
        $this->dropIndex('users', 'users_last_login_idx');
    }

    protected function createIndex(string $table, string $name, array $columns): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)',
                $name,
                $table,
                implode(', ', $columns),
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    protected function dropIndex(string $table, string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }
};
