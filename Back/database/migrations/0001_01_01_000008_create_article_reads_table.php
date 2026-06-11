<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_reads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->smallInteger('read_percent')->default(0);
            $table->integer('reading_seconds')->default(0);
            $table->integer('points_earned')->default(0);
            $table->boolean('counted_for_payout')->default(false);
            $table->string('session_id', 128)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('article_id');
            $table->index('user_id');
            $table->index('counted_for_payout');
            $table->index('created_at');
            $table->index(['article_id', 'user_id']);
            $table->index(['article_id', 'counted_for_payout', 'created_at']);
            $table->index('session_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE article_reads ADD CONSTRAINT article_reads_read_percent_check CHECK (read_percent >= 0 AND read_percent <= 100)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE article_reads ADD CONSTRAINT article_reads_reading_seconds_check CHECK (reading_seconds >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE article_reads ADD CONSTRAINT article_reads_points_earned_check CHECK (points_earned >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_reads');
    }
};
