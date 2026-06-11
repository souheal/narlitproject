<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('payout_batch_id')->constrained('payout_batches')->restrictOnDelete();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles')->restrictOnDelete();
            $table->decimal('engagement_score', 12, 4);
            $table->decimal('payout_amount', 12, 2);
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->string('stripe_transfer_id')->nullable()->unique();
            $table->string('transfer_status', 50)->nullable();
            $table->timestampTz('transferred_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index('payout_batch_id');
            $table->index('organization_profile_id');
            $table->unique(['payout_batch_id', 'organization_profile_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_items ADD CONSTRAINT payout_items_engagement_score_check CHECK (engagement_score >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_items ADD CONSTRAINT payout_items_payout_amount_check CHECK (payout_amount >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_items ADD CONSTRAINT payout_items_total_reads_check CHECK (total_reads >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payout_items ADD CONSTRAINT payout_items_total_points_check CHECK (total_points >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
