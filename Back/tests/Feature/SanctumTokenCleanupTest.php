<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanctumTokenCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_expired_sanctum_tokens_are_deleted_without_deleting_valid_tokens(): void
    {
        DB::table('personal_access_tokens')->insert([
            [
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => 1,
                'name' => 'expired',
                'token' => str_repeat('a', 64),
                'abilities' => json_encode(['*']),
                'expires_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => 1,
                'name' => 'valid',
                'token' => str_repeat('b', 64),
                'abilities' => json_encode(['*']),
                'expires_at' => now()->addMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => 1,
                'name' => 'no-expiration',
                'token' => str_repeat('c', 64),
                'abilities' => json_encode(['*']),
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('sanctum:cleanup-expired-tokens')
            ->expectsOutput('Deleted 1 expired Sanctum tokens.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'expired']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'valid']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'no-expiration']);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
}
