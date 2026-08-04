<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\PhoneMfaService;
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
        config()->set('auth.admin_token_ttl', 240);
        config()->set('auth.user_token_ttl', 43200);
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

        $this->getJson('/api/v1/admin/analytics/overview')
            ->assertForbidden()
            ->assertJsonPath('message', 'Administrator MFA enrollment is required.')
            ->assertJsonPath('data.next_step', 'mfa_enrollment_required');
    }

    public function test_admin_login_requires_phone_mfa_before_token_and_cookie_are_issued(): void
    {
        $admin = $this->userWithRole('admin', 'admin-login@test.com', false);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.token_type');

        $this->assertNull(collect($login->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'admin_token'));
        $this->assertSame(0, $admin->tokens()->count());

        $admin->refresh();
        $this->assertNotNull($admin->phone_mfa_code);
        $this->assertNotNull($admin->phone_mfa_expires_at);
    }

    public function test_admin_mfa_verification_uses_secure_http_only_cookie_and_logout_removes_it(): void
    {
        $admin = $this->userWithRole('admin', 'admin-mfa@test.com', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertOk();

        $mfaCode = app(PhoneMfaService::class)->previewForUser($admin->refresh());

        $login = $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $mfaCode,
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('message', 'Phone verification completed.')
            ->assertJsonPath('data.next_step', 'completed')
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.mfa_enrolled', true);

        $cookie = collect($login->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'admin_token');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $this->assertSame('/', $cookie->getPath());
        $this->assertGreaterThan(now()->addMinutes(239)->timestamp, $cookie->getExpiresTime());
        $this->assertLessThanOrEqual(now()->addMinutes(240)->timestamp, $cookie->getExpiresTime());
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertTrue($admin->tokens()->firstOrFail()->expires_at->between(
            now()->addMinutes(239),
            now()->addMinutes(240),
        ));
        $this->assertNotNull($admin->refresh()->mfa_enrolled_at);
        $this->assertNull($admin->phone_mfa_code);
        $this->assertNull($admin->phone_mfa_expires_at);

        $logout = $this->withCredentials()
            ->withUnencryptedCookie('admin_token', $cookie->getValue())
            ->postJson('/api/v1/auth/logout');

        $logout->assertOk();

        $expiredCookie = collect($logout->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'admin_token');

        $this->assertNotNull($expiredCookie);
        $this->assertLessThan(now()->timestamp, $expiredCookie->getExpiresTime());
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_enrolled_admin_still_requires_phone_mfa_on_every_login(): void
    {
        $admin = $this->userWithRole('admin', 'enrolled-admin@test.com', true);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.token');

        $this->assertSame(0, $admin->tokens()->count());
        $this->assertNotNull($admin->refresh()->phone_mfa_code);
    }

    public function test_admin_without_phone_number_cannot_receive_access(): void
    {
        $admin = $this->userWithRole('admin', 'no-phone-admin@test.com', false, true, false, null);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Phone verification is required for administrator accounts. Please contact support.');

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_admin_mfa_rejects_invalid_expired_replayed_and_cross_user_codes(): void
    {
        $admin = $this->userWithRole('admin', 'mfa-invalid@test.com', false);
        $otherAdmin = $this->userWithRole('admin', 'mfa-other@test.com', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertOk();

        $code = app(PhoneMfaService::class)->previewForUser($admin->refresh());

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please enter a valid verification code.');

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $otherAdmin->email,
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please request a new phone verification code.');

        $admin->forceFill(['phone_mfa_expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The verification code has expired.');

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertOk();

        $freshCode = app(PhoneMfaService::class)->previewForUser($admin->refresh());

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $freshCode,
        ])->assertOk();

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $freshCode,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please request a new phone verification code.');
    }

    public function test_admin_can_refresh_session_and_rotate_token_cookie(): void
    {
        $admin = $this->userWithRole('admin', 'refresh-admin@test.com', true);
        $oldPlainTextToken = $admin->createToken('web', ['*'], now()->addMinutes(30))->plainTextToken;
        $oldTokenId = $admin->tokens()->firstOrFail()->id;

        $response = $this->withCredentials()
            ->withUnencryptedCookie('admin_token', $oldPlainTextToken)
            ->postJson('/api/v1/auth/refresh');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Admin session refreshed successfully.')
            ->assertJsonPath('data.expires_in_minutes', 240)
            ->assertJsonMissingPath('data.token');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'admin_token');

        $this->assertNotNull($cookie);
        $this->assertNotSame($oldPlainTextToken, $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $this->assertGreaterThan(now()->addMinutes(239)->timestamp, $cookie->getExpiresTime());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldTokenId]);
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertTrue($admin->tokens()->firstOrFail()->expires_at->between(
            now()->addMinutes(239),
            now()->addMinutes(240),
        ));
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'auth.session_refreshed',
            'entity_type' => 'user',
            'entity_id' => $admin->public_id,
        ]);
    }

    public function test_admin_refresh_rejects_guest_member_inactive_missing_mfa_and_expired_tokens(): void
    {
        $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();

        $member = $this->userWithRole('member', 'member-refresh@test.com', true);
        $memberToken = $member->createToken('web', ['*'], now()->addMinutes(43200))->plainTextToken;
        $this->withToken($memberToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access is required.');
        $this->app['auth']->forgetGuards();

        $inactiveAdmin = $this->userWithRole('admin', 'inactive-refresh@test.com', true, false);
        $inactiveToken = $inactiveAdmin->createToken('web', ['*'], now()->addMinutes(240))->plainTextToken;
        $this->flushHeaders()
            ->withCredentials()
            ->withUnencryptedCookie('admin_token', $inactiveToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin account is not active.');
        $this->app['auth']->forgetGuards();

        $mfaAdmin = $this->userWithRole('admin', 'mfa-refresh@test.com', false);
        $mfaToken = $mfaAdmin->createToken('web', ['*'], now()->addMinutes(240))->plainTextToken;
        $this->flushHeaders()
            ->withCredentials()
            ->withUnencryptedCookie('admin_token', $mfaToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden()
            ->assertJsonPath('message', 'Administrator MFA enrollment is required.');
        $this->app['auth']->forgetGuards();

        $expiredAdmin = $this->userWithRole('admin', 'expired-refresh@test.com', true);
        $expiredToken = $expiredAdmin->createToken('web', ['*'], now()->subMinute())->plainTextToken;
        $this->flushHeaders()
            ->withCredentials()
            ->withUnencryptedCookie('admin_token', $expiredToken)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized();

        $this->flushHeaders()
            ->withCredentials()
            ->withUnencryptedCookie('admin_token', $expiredToken)
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertUnauthorized();
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

        $content = $this->get('/api/v1/admin/audit/export')->assertOk()->streamedContent();

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
            $table->string('phone_mfa_code')->nullable();
            $table->timestamp('phone_mfa_expires_at')->nullable();
            $table->timestamp('phone_mfa_verified_at')->nullable();
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->timestamp('mfa_enrolled_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
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

    private function userWithRole(
        string $role,
        string $email,
        bool $mfaComplete,
        bool $isActive = true,
        bool $mfaEnrolled = true,
        ?string $phone = '+10000000000',
    ): User {
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
            'phone' => $phone,
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => $isActive,
            'first_login_mfa_completed_at' => $mfaComplete ? now() : null,
            'mfa_enrolled_at' => $mfaComplete && $mfaEnrolled ? now() : null,
            'failed_login_attempts' => 0,
        ]);
    }
}
