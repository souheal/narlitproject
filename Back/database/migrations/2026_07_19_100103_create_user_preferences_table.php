<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('categories')->nullable();
            $table->json('followed_organizations')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(false);
            $table->boolean('email_new_articles')->default(true);
            $table->boolean('email_new_from_supported')->default(true);
            $table->boolean('email_impact_summary')->default(true);
            $table->boolean('email_product_updates')->default(false);
            $table->boolean('push_new_articles')->default(false);
            $table->boolean('push_achievements')->default(true);
            $table->boolean('push_payment_events')->default(true);
            $table->string('digest_frequency', 20)->default('weekly');
            $table->string('email_digest', 20)->default('weekly');
            $table->string('theme', 20)->default('system');
            $table->string('language', 8)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('monthly_reading_goal')->default(10);
            $table->timestampTz('onboarded_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
