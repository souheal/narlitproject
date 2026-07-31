<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_mfa_is_required_for_admin_routes(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com', false));

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin MFA verification is required.');
    }

    public function test_admin_cannot_reset_own_mfa_or_revoke_own_tokens(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        $admin->createToken('web');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$admin->public_id}/reset-mfa")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot reset MFA for your own active admin session.');

        $this->deleteJson("/api/v1/admin/users/{$admin->public_id}/tokens")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot revoke tokens for your own active admin session.');

        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_audit_csv_export_escapes_formula_values(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com', true);
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => '=dangerous_formula',
            'entity_type' => 'user',
            'entity_id' => '+target',
            'ip_address' => '127.0.0.1',
            'user_agent' => '@agent',
            'metadata' => json_encode(['note' => '-formula']),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $content = $this->get('/api/v1/admin/audit-logs/export')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=dangerous_formula", $content);
        $this->assertStringContainsString("'+target", $content);
        $this->assertStringContainsString("'@agent", $content);
        $this->assertStringContainsString('"{""note"":""-formula""}"', $content);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('role_id')->constrained('roles');
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function userWithRole(string $role, string $email, bool $mfaComplete): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => str($role)->headline().' User',
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => $mfaComplete ? now() : null,
            'failed_login_attempts' => 0,
        ]);
    }
}
