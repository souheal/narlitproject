<?php

namespace Tests\Feature\Admin;

use App\Services\Auth\PhoneMfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\InteractsWithCriticalAdminData;
use Tests\TestCase;

class AdminCriticalAuthenticationTest extends TestCase
{
    use InteractsWithCriticalAdminData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        config()->set('auth.admin_token_ttl', 240);
        $this->prepareCriticalAdminTest();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_password_login_starts_mfa_without_issuing_access_token(): void
    {
        $admin = $this->createAdminWithRole('super_admin', [
            'email' => 'mfa-required@admin.test',
            'first_login_mfa_completed_at' => null,
            'mfa_enrolled_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.token');

        $this->assertNull(collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'admin_token'));
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertNotNull($admin->refresh()->phone_mfa_code);
    }

    public function test_admin_login_rejects_invalid_inactive_unverified_and_no_phone_accounts(): void
    {
        $admin = $this->createAdminWithRole('super_admin', ['email' => 'login-fail@admin.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $inactive = $this->createAdminWithRole('super_admin', [
            'email' => 'inactive@admin.test',
            'is_active' => false,
        ]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $inactive->email,
            'password' => 'Password123!',
        ])->assertForbidden();

        $unverified = $this->createAdminWithRole('super_admin', [
            'email' => 'unverified@admin.test',
            'email_verified_at' => null,
        ]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $unverified->email,
            'password' => 'Password123!',
        ])->assertForbidden();

        $noPhone = $this->createAdminWithRole('super_admin', [
            'email' => 'no-phone@admin.test',
            'phone' => null,
        ]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $noPhone->email,
            'password' => 'Password123!',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Phone verification is required for administrator accounts. Please contact support.');
    }

    public function test_valid_mfa_issues_secure_admin_cookie_and_rejects_reuse(): void
    {
        $admin = $this->createAdminWithRole('super_admin', [
            'email' => 'mfa-complete@admin.test',
            'mfa_enrolled_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertOk();

        $code = app(PhoneMfaService::class)->previewForUser($admin->refresh());

        $response = $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $code,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.next_step', 'completed')
            ->assertJsonPath('data.mfa_enrolled', true)
            ->assertJsonPath('data.token', null);

        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'admin_token');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $this->assertSame(1, $admin->tokens()->count());
        $this->assertNotNull($admin->refresh()->mfa_enrolled_at);

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_invalid_expired_and_cross_user_mfa_codes_are_rejected(): void
    {
        $admin = $this->createAdminWithRole('super_admin', ['email' => 'mfa-invalid@admin.test']);
        $otherAdmin = $this->createAdminWithRole('super_admin', ['email' => 'mfa-other@admin.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertOk();

        $code = app(PhoneMfaService::class)->previewForUser($admin->refresh());

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => '000000',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $otherAdmin->email,
            'code' => $code,
        ])->assertStatus(422);

        $admin->forceFill(['phone_mfa_expires_at' => now()->subMinute()])->save();
        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => $admin->email,
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The verification code has expired.');
    }

    public function test_admin_routes_require_auth_admin_role_completed_mfa_and_non_expired_token(): void
    {
        $this->getJson('/api/v1/admin/analytics/overview')->assertUnauthorized();

        Sanctum::actingAs($this->createUserWithRole('subscriber'));
        $this->getJson('/api/v1/admin/analytics/overview')->assertForbidden();

        Sanctum::actingAs($this->createUserWithRole('organization'));
        $this->getJson('/api/v1/admin/analytics/overview')->assertForbidden();

        Sanctum::actingAs($this->createAdminWithRole('super_admin', ['mfa_enrolled_at' => null]));
        $this->getJson('/api/v1/admin/analytics/overview')
            ->assertForbidden()
            ->assertJsonPath('data.next_step', 'mfa_enrollment_required');

        $this->app['auth']->forgetGuards();
        $expiredAdmin = $this->createAdminWithRole('super_admin');
        $expiredToken = $expiredAdmin->createToken('web', ['*'], now()->subMinute())->plainTextToken;
        $this->flushHeaders()
            ->withCredentials()
            ->withUnencryptedCookie('admin_token', $expiredToken)
            ->getJson('/api/v1/admin/analytics/overview')
            ->assertUnauthorized();
    }

    public function test_admin_refresh_and_logout_rotate_and_revoke_tokens(): void
    {
        $admin = $this->createAdminWithRole('super_admin');
        $oldToken = $admin->createToken('web', ['*'], now()->addMinutes(30))->plainTextToken;

        $refresh = $this->withCredentials()
            ->withUnencryptedCookie('admin_token', $oldToken)
            ->postJson('/api/v1/auth/refresh');

        $refresh
            ->assertOk()
            ->assertJsonPath('data.expires_in_minutes', 240)
            ->assertJsonMissingPath('data.token');

        $newCookie = collect($refresh->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'admin_token');
        $this->assertNotNull($newCookie);
        $this->assertSame(1, $admin->tokens()->count());

        $this->app['auth']->forgetGuards();
        $logout = $this->withCredentials()
            ->withUnencryptedCookie('admin_token', $newCookie->getValue())
            ->postJson('/api/v1/auth/logout');

        $logout->assertOk();
        $this->assertSame(0, $admin->refresh()->tokens()->count());
    }
}
