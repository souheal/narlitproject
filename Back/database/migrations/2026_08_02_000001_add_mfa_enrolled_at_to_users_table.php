<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'mfa_enrolled_at')) {
                $table->timestampTz('mfa_enrolled_at')->nullable()->after('first_login_mfa_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'mfa_enrolled_at')) {
                $table->dropColumn('mfa_enrolled_at');
            }
        });
    }
};
