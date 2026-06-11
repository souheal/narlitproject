<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->decimal('total_impact_amount', 12, 2)->default(0);
            $table->bigInteger('total_articles_read')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->bigInteger('total_organizations_supported')->default(0);
            $table->timestampsTz();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_wallets ADD CONSTRAINT impact_wallets_total_impact_amount_check CHECK (total_impact_amount >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_wallets ADD CONSTRAINT impact_wallets_total_articles_read_check CHECK (total_articles_read >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_wallets ADD CONSTRAINT impact_wallets_total_points_check CHECK (total_points >= 0)');
        }
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE impact_wallets ADD CONSTRAINT impact_wallets_total_organizations_supported_check CHECK (total_organizations_supported >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_wallets');
    }
};
