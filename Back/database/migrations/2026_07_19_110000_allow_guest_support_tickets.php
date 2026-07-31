<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('guest_name', 120)->nullable()->after('user_id');
                $table->string('guest_email', 255)->nullable()->after('guest_name');
            });

            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_name', 120)->nullable()->after('user_id');
            $table->string('guest_email', 255)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['guest_name', 'guest_email']);
        });
    }
};
