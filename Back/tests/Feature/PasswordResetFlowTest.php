<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_user_can_reset_password_with_email_otp_and_existing_tokens_are_invalidated(): void
    {
        $user = $this->user();
        $token = $user->createToken('existing-token')->plainTextToken;

        $forgot = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@test.com',
        ]);

        $forgot
            ->assertOk()
            ->assertJsonPath('message', 'Password reset code sent to your email.')
            ->assertJsonPath('next_step', 'verify_reset_otp')
            ->assertJsonStructure(['otp']);

        $otp = $forgot->json('otp');
        $user->refresh();

        $this->assertNotSame($otp, $user->password_reset_otp_code);
        $this->assertTrue(Hash::check($otp, $user->password_reset_otp_code));
        $this->assertNotNull($user->password_reset_otp_expires_at);
        $this->assertNull($user->password_reset_otp_verified_at);

        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'reset@test.com',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset code verified successfully.')
            ->assertJsonPath('next_step', 'reset_password');

        $this->assertNotNull($user->refresh()->password_reset_otp_verified_at);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@test.com',
            'otp' => $otp,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.')
            ->assertJsonPath('next_step', 'login');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertNull($user->password_reset_otp_code);
        $this->assertNull($user->password_reset_otp_expires_at);
        $this->assertNull($user->password_reset_otp_verified_at);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'existing-token',
        ]);
        $this->assertNotEmpty($token);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@test.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset code sent to your email.')
            ->assertJsonPath('next_step', 'verify_reset_otp')
            ->assertJsonMissingPath('otp');
    }

    public function test_invalid_reset_otp_is_rejected(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => 'reset@test.com',
            'otp' => '123456',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The verification code is invalid.');
    }

    private function createTestSchema(): void
    {
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
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone_mfa_code')->nullable();
            $table->timestamp('phone_mfa_expires_at')->nullable();
            $table->timestamp('phone_mfa_verified_at')->nullable();
            $table->string('password_reset_otp_code')->nullable();
            $table->timestamp('password_reset_otp_expires_at')->nullable();
            $table->timestamp('password_reset_otp_verified_at')->nullable();
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->boolean('is_active')->default(false);
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
    }

    private function user(): User
    {
        $roleId = Schema::getConnection()
            ->table('roles')
            ->insertGetId([
                'name' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => 'Reset User',
            'username' => 'reset_user',
            'email' => 'reset@test.com',
            'phone' => '09376635271',
            'password' => Hash::make('OldPassword123!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => now(),
            'failed_login_attempts' => 0,
        ]);
    }
}
