<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles')->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->bigInteger('points_generated')->default(0);
            $table->date('transaction_month');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('organization_profile_id');
            $table->index('article_id');
            $table->index('payment_id');
            $table->index('transaction_month');
            $table->index(['organization_profile_id', 'transaction_month']);
            $table->index(['user_id', 'transaction_month']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_transactions ADD CONSTRAINT impact_transactions_amount_check CHECK (amount >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_transactions ADD CONSTRAINT impact_transactions_points_generated_check CHECK (points_generated >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE impact_transactions ADD CONSTRAINT impact_transactions_transaction_month_check CHECK (transaction_month = date_trunc('month', transaction_month::timestamp)::date)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_transactions');
    }
};
