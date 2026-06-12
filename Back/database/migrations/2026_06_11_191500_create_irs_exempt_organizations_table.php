<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irs_exempt_organizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ein', 9)->unique();
            $table->string('organization_name');
            $table->string('normalized_name')->index();
            $table->string('city')->nullable();
            $table->string('state', 16)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('subsection')->nullable();
            $table->string('classification')->nullable();
            $table->string('ruling_date')->nullable();
            $table->string('deductibility')->nullable();
            $table->string('foundation_code')->nullable();
            $table->string('activity_code')->nullable();
            $table->string('organization_code')->nullable();
            $table->string('source')->default('irs_eo_bmf');
            $table->timestampTz('imported_at');
            $table->json('raw')->nullable();
            $table->timestampsTz();

            $table->index(['ein', 'normalized_name']);
            $table->index('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irs_exempt_organizations');
    }
};
