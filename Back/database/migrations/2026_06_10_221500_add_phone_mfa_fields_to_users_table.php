<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_mfa_code')) {
                $table->string('phone_mfa_code')->nullable()->after('otp_expires_at');
            }

            if (! Schema::hasColumn('users', 'phone_mfa_expires_at')) {
                $table->timestampTz('phone_mfa_expires_at')->nullable()->after('phone_mfa_code');
            }

            if (! Schema::hasColumn('users', 'phone_mfa_verified_at')) {
                $table->timestampTz('phone_mfa_verified_at')->nullable()->after('phone_mfa_expires_at');
            }

            if (! Schema::hasColumn('users', 'first_login_mfa_completed_at')) {
                $table->timestampTz('first_login_mfa_completed_at')->nullable()->after('phone_mfa_verified_at');
            }
        });

        $this->makeLegacySubscriberColumnsNullable();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'first_login_mfa_completed_at',
                'phone_mfa_verified_at',
                'phone_mfa_expires_at',
                'phone_mfa_code',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeLegacySubscriberColumnsNullable(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            foreach (['username', 'gender'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    DB::statement("ALTER TABLE users ALTER COLUMN {$column} DROP NOT NULL");
                }
            }

            return;
        }

        if ($driver === 'mysql') {
            if (Schema::hasColumn('users', 'username')) {
                DB::statement('ALTER TABLE users MODIFY username VARCHAR(50) NULL');
            }

            if (Schema::hasColumn('users', 'gender')) {
                DB::statement("ALTER TABLE users MODIFY gender ENUM('male', 'female', 'other') NULL");
            }
        }

        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'username')) {
                    $table->string('username', 50)->nullable()->change();
                }

                if (Schema::hasColumn('users', 'gender')) {
                    $table->string('gender')->nullable()->change();
                }
            });
        }
    }
};
