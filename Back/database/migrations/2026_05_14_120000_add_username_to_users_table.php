<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 50)->nullable()->after('full_name');
            });

            DB::statement("
                UPDATE users
                SET username = LOWER(
                    REGEXP_REPLACE(
                        SPLIT_PART(email, '@', 1) || '_' || id::text,
                        '[^a-zA-Z0-9_]+',
                        '_',
                        'g'
                    )
                )
                WHERE username IS NULL
            ");

            DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_username_unique UNIQUE (username)');
            DB::statement('CREATE INDEX users_username_index ON users (username)');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_username_index');
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            });
        }
    }
};
