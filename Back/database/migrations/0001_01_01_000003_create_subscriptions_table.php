<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->unique();
            $table->enum('plan', ['monthly', 'yearly']);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['active', 'canceled', 'past_due', 'unpaid', 'incomplete'])->default('incomplete');
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index(['status', 'expires_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_amount_check CHECK (amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
