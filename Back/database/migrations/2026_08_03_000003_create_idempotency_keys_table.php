<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('key');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation');
            $table->string('request_method', 10);
            $table->string('request_path_hash', 64);
            $table->string('request_fingerprint', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['actor_id', 'key']);
            $table->index(['status', 'expires_at']);
            $table->index('operation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
