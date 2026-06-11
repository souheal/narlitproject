<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category', 120)->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('featured_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->smallInteger('read_time')->nullable();
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_unique_reads')->default(0);
            $table->bigInteger('total_reading_seconds')->default(0);
            $table->bigInteger('total_points_generated')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('organization_profile_id');
            $table->index('status');
            $table->index('published_at');
            $table->index('featured_at');
            $table->index(['status', 'published_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_read_time_check CHECK (read_time IS NULL OR read_time >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_total_reads_check CHECK (total_reads >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_total_unique_reads_check CHECK (total_unique_reads >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_total_reading_seconds_check CHECK (total_reading_seconds >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE articles ADD CONSTRAINT articles_total_points_generated_check CHECK (total_points_generated >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
