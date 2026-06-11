<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\PhoneMfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSubscriberFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_user_signup_payment_first_login_phone_mfa_and_future_login_flow(): void
    {
        config()->set('services.stripe.fake_checkout', true);
        config()->set('services.stripe.monthly_amount', 700);

        $registration = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Muhannad Test',
            'email' => 'muhannad@test.com',
            'phone' => '09376635271',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'subscription_plan' => 'monthly',
        ]);

        $registration
            ->assertCreated()
            ->assertJsonPath('data.next_step', 'verify_email')
            ->assertJsonMissingPath('data.user.username');

        $user = User::query()->where('email', 'muhannad@test.com')->firstOrFail();
        $this->assertSame('muhannad', $user->username);

        $otp = app(OtpService::class)->previewForUser($user);

        $this->assertNotNull($otp);

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'muhannad@test.com',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'payment');

        $this->postJson('/api/v1/billing/checkout', [
            'email' => 'muhannad@test.com',
            'subscription_plan' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'fake')
            ->assertJsonPath('data.amount', '7.00')
            ->assertJsonPath('data.currency', 'USD');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $firstLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'muhannad@test.com',
            'password' => 'Password123!',
        ]);

        $firstLogin
            ->assertOk()
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.token');

        $user->refresh();
        $mfaCode = app(PhoneMfaService::class)->previewForUser($user);

        $this->assertNotNull($mfaCode);
        $this->assertNotNull($user->phone_mfa_code);
        $this->assertNotSame($mfaCode, $user->phone_mfa_code);

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => 'muhannad@test.com',
            'code' => $mfaCode,
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'completed')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotNull($user->refresh()->first_login_mfa_completed_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'muhannad@test.com',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'completed')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->count());
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('subscriptions');
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

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->unique();
            $table->string('plan');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('incomplete');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
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
}
