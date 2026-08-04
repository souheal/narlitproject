<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
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

    public function test_admin_can_filter_audit_logs_and_sensitive_metadata_is_redacted(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $otherAdmin = $this->userWithRole('admin', 'other-admin@test.com');
        $targetId = (string) str()->uuid();
        $logId = DB::table('admin_logs')->insertGetId([
            'admin_id' => $admin->id,
            'action' => 'user.tokens_revoked',
            'entity_type' => 'user',
            'entity_id' => $targetId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Windows Chrome',
            'metadata' => json_encode([
                'tokens_revoked' => 2,
                'password' => 'secret',
                'nested' => [
                    'authorization_header' => 'Bearer token',
                    'safe' => 'visible',
                ],
            ]),
            'created_at' => now()->subHour(),
        ]);
        DB::table('admin_logs')->insert([
            'admin_id' => $otherAdmin->id,
            'action' => 'payment.refund_failed',
            'entity_type' => 'payment',
            'entity_id' => 'pay_123',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 Mac Firefox',
            'metadata' => json_encode(['status' => 'failure', 'stripe_secret' => 'sk_test_secret']),
            'created_at' => now()->subMinutes(30),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/audit?action=user.tokens_revoked&entity_type=user&ip_address=127.0.0.1&search=tokens&direction=asc&per_page=5')
            ->assertOk()
            ->assertJsonPath('message', 'Audit logs retrieved successfully.')
            ->assertJsonPath('data.audit_logs.data.0.id', $logId)
            ->assertJsonPath('data.audit_logs.data.0.actor.email', 'admin@test.com')
            ->assertJsonPath('data.audit_logs.data.0.status', 'success')
            ->assertJsonPath('data.audit_logs.data.0.metadata.password', '[REDACTED]')
            ->assertJsonPath('data.audit_logs.data.0.metadata.nested.authorization_header', '[REDACTED]')
            ->assertJsonPath('data.audit_logs.data.0.metadata.nested.safe', 'visible')
            ->assertJsonPath('data.audit_logs.data.0.target_admin_path', "/admin/users/{$targetId}")
            ->assertJsonPath('data.audit_logs.meta.per_page', 5);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'audit_log.viewed',
            'entity_type' => 'audit_log',
        ]);
    }

    public function test_admin_can_view_single_log_and_export_filtered_csv(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $logId = DB::table('admin_logs')->insertGetId([
            'admin_id' => $admin->id,
            'action' => 'payment.refund_failed',
            'entity_type' => 'payment',
            'entity_id' => 'pay_123',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Windows Chrome',
            'metadata' => json_encode([
                'status' => 'failure',
                'stripe_secret' => 'sk_test_secret',
                'reason' => 'Gateway failure',
            ]),
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/audit/{$logId}")
            ->assertOk()
            ->assertJsonPath('data.audit_log.id', $logId)
            ->assertJsonPath('data.audit_log.status', 'failure')
            ->assertJsonPath('data.audit_log.metadata.stripe_secret', '[REDACTED]')
            ->assertJsonPath('data.audit_log.metadata.reason', 'Gateway failure');

        $response = $this->get('/api/v1/admin/audit/export?status=failure');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringContainsString('payment.refund_failed', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('sk_test_secret', $content);
        $this->assertStringContainsString('Gateway failure', $content);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'audit_log.exported',
            'entity_type' => 'audit_log',
        ]);
    }

    public function test_audit_logs_require_admin_access(): void
    {
        $this->getJson('/api/v1/admin/audit')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com'));

        $this->getJson('/api/v1/admin/audit')->assertForbidden();
    }

    public function test_duplicate_audit_logs_route_is_removed(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com'));

        $this->getJson('/api/v1/admin/audit-logs')->assertNotFound();
        $this->get('/api/v1/admin/audit-logs/export')->assertNotFound();
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

    private function userWithRole(string $role, string $email): User
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
            'first_login_mfa_completed_at' => now(),
            'failed_login_attempts' => 0,
        ]);
    }
}
