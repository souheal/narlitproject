<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->date('batch_month')->unique();
            $table->decimal('total_pool', 12, 2)->default(0);
            $table->decimal('total_distributed', 12, 2)->default(0);
            $table->bigInteger('total_organizations')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_total_pool_check CHECK (total_pool >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_total_distributed_check CHECK (total_distributed >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_total_organizations_check CHECK (total_organizations >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_distribution_balance_check CHECK (total_distributed <= total_pool)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payout_batches ADD CONSTRAINT payout_batches_batch_month_check CHECK (batch_month = date_trunc('month', batch_month::timestamp)::date)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
