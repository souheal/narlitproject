<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'password_reset_otp_code')) {
                $table->string('password_reset_otp_code')->nullable()->after('phone_mfa_verified_at');
            }

            if (! Schema::hasColumn('users', 'password_reset_otp_expires_at')) {
                $table->timestampTz('password_reset_otp_expires_at')->nullable()->after('password_reset_otp_code');
            }

            if (! Schema::hasColumn('users', 'password_reset_otp_verified_at')) {
                $table->timestampTz('password_reset_otp_verified_at')->nullable()->after('password_reset_otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'password_reset_otp_verified_at',
                'password_reset_otp_expires_at',
                'password_reset_otp_code',
            ]);
        });
    }
};
