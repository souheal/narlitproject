<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EXPIRES_INDEX = 'users_checkout_token_expires_at_index';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'checkout_token_hash')) {
                $table->string('checkout_token_hash')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'checkout_token_expires_at')) {
                $table->timestampTz('checkout_token_expires_at')->nullable()->after('checkout_token_hash');
            }

            if (! Schema::hasColumn('users', 'checkout_token_consumed_at')) {
                $table->timestampTz('checkout_token_consumed_at')->nullable()->after('checkout_token_expires_at');
            }

            if (! Schema::hasColumn('users', 'checkout_replay_message')) {
                $table->string('checkout_replay_message')->nullable()->after('checkout_token_consumed_at');
            }

            if (! Schema::hasColumn('users', 'checkout_replay_data')) {
                $table->json('checkout_replay_data')->nullable()->after('checkout_replay_message');
            }

            if (! $this->hasIndex('users', self::EXPIRES_INDEX)) {
                $table->index('checkout_token_expires_at', self::EXPIRES_INDEX);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasIndex('users', self::EXPIRES_INDEX)) {
                $table->dropIndex(self::EXPIRES_INDEX);
            }

            $table->dropColumn([
                'checkout_token_hash',
                'checkout_token_expires_at',
                'checkout_token_consumed_at',
                'checkout_replay_message',
                'checkout_replay_data',
            ]);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existing): bool => ($existing['name'] ?? null) === $index);
    }
};
