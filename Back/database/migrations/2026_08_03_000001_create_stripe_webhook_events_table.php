<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->string('status', 32);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index('event_type');
            $table->index('status');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
