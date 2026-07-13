<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payout_batches')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payout_batches DROP CONSTRAINT IF EXISTS payout_batches_status_check');
            DB::statement("ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'canceled'))");
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payout_batches MODIFY status ENUM('pending', 'processing', 'completed', 'failed', 'canceled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payout_batches')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payout_batches DROP CONSTRAINT IF EXISTS payout_batches_status_check');
            DB::statement("ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE payout_batches MODIFY status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending'");
        }
    }
};
