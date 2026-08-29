<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Billing\CheckoutContinuationTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountEnumerationHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_email_otp_verification_does_not_reveal_missing_registration(): void
    {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'missing@test.com',
            'otp' => '123456',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please enter a valid verification code.');

        $this->assertStringNotContainsString('registration', strtolower((string) $response->json('message')));
        $this->assertStringNotContainsString('account', strtolower((string) $response->json('message')));
    }

    public function test_email_otp_resend_does_not_reveal_missing_registration(): void
    {
        $existing = $this->user('existing-otp@test.com');

        $existingResponse = $this->postJson('/api/v1/auth/resend-otp', [
            'email' => $existing->email,
        ]);

        $response = $this->postJson('/api/v1/auth/resend-otp', [
            'email' => 'missing@test.com',
        ]);

        $existingResponse
            ->assertOk()
            ->assertJsonPath('message', 'A new verification code has been sent.')
            ->assertJsonPath('data.next_step', 'verify_email')
            ->assertJsonMissingPath('data.otp_expires_at');

        $response
            ->assertOk()
            ->assertJsonPath('message', $existingResponse->json('message'))
            ->assertJsonPath('data.next_step', 'verify_email')
            ->assertJsonMissingPath('data.otp_expires_at');

        $this->assertSame(
            array_keys($existingResponse->json('data')),
            array_keys($response->json('data')),
        );

        $this->assertStringNotContainsString('registration', strtolower((string) $response->json('message')));
        $this->assertStringNotContainsString('account', strtolower((string) $response->json('message')));
    }

    public function test_phone_mfa_verification_does_not_reveal_missing_account(): void
    {
        $response = $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => 'missing@test.com',
            'code' => '123456',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please enter a valid verification code.');

        $this->assertStringNotContainsString('account', strtolower((string) $response->json('message')));
    }

    public function test_phone_mfa_resend_does_not_reveal_missing_or_ineligible_account(): void
    {
        $eligible = $this->user('eligible-phone@test.com', emailVerified: true);
        $unverified = $this->user('unverified@test.com');

        DB::table('subscriptions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $eligible->id,
            'stripe_customer_id' => 'cus_phone_mfa',
            'stripe_subscription_id' => 'sub_phone_mfa',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existing = $this->postJson('/api/v1/auth/resend-phone-mfa', [
            'email' => $eligible->email,
        ]);

        $missing = $this->postJson('/api/v1/auth/resend-phone-mfa', [
            'email' => 'missing@test.com',
        ]);

        $ineligible = $this->postJson('/api/v1/auth/resend-phone-mfa', [
            'email' => $unverified->email,
        ]);

        $existing
            ->assertOk()
            ->assertJsonPath('message', 'A new phone verification code has been sent.')
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.phone_mfa_expires_at');

        $missing
            ->assertOk()
            ->assertJsonPath('message', $existing->json('message'))
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.phone_mfa_expires_at');

        $ineligible
            ->assertOk()
            ->assertJsonPath('message', $existing->json('message'))
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.phone_mfa_expires_at');

        $this->assertSame(array_keys($existing->json('data')), array_keys($missing->json('data')));
        $this->assertSame(array_keys($existing->json('data')), array_keys($ineligible->json('data')));

        $this->assertNull($unverified->refresh()->phone_mfa_code);
    }

    public function test_checkout_does_not_reveal_missing_or_ineligible_registration(): void
    {
        $unverified = $this->user('unverified@test.com');
        $subscribed = $this->user('subscribed@test.com', emailVerified: true);
        $verified = $this->user('verified-unpaid@test.com', emailVerified: true);

        DB::table('subscriptions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $subscribed->id,
            'stripe_customer_id' => 'cus_existing',
            'stripe_subscription_id' => 'sub_existing',
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $missing = $this->postJson('/api/v1/billing/checkout', [
            'email' => 'missing@test.com',
            'subscription_plan' => 'monthly',
        ]);

        $notVerified = $this->postJson('/api/v1/billing/checkout', [
            'email' => $unverified->email,
            'subscription_plan' => 'monthly',
        ]);

        $alreadySubscribed = $this->postJson('/api/v1/billing/checkout', [
            'email' => $subscribed->email,
            'subscription_plan' => 'monthly',
        ]);

        $verifiedWithoutToken = $this->postJson('/api/v1/billing/checkout', [
            'email' => $verified->email,
            'subscription_plan' => 'monthly',
        ]);

        $verifiedInvalidToken = $this->postJson('/api/v1/billing/checkout', [
            'email' => $verified->email,
            'checkout_token' => str_repeat('a', 64),
            'subscription_plan' => 'monthly',
        ]);

        foreach ([$missing, $notVerified, $alreadySubscribed, $verifiedWithoutToken, $verifiedInvalidToken] as $response) {
            $response
                ->assertStatus(202)
                ->assertJsonPath('message', 'Checkout request received. If eligible, checkout can continue.')
                ->assertJsonPath('data.next_step', 'check_registration_status');

            $this->assertStringNotContainsString('registration', strtolower((string) $response->json('message')));
            $this->assertStringNotContainsString('account', strtolower((string) $response->json('message')));
        }
    }

    public function test_successful_otp_verification_returns_hashed_checkout_continuation_token(): void
    {
        $user = $this->user('otp-token@test.com');
        $otp = '123456';
        $user->forceFill([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $token = (string) $response
            ->assertOk()
            ->assertJsonPath('data.next_step', 'checkout')
            ->assertJsonStructure(['data' => ['checkout_token', 'checkout_token_expires_at']])
            ->json('data.checkout_token');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        $user->refresh();
        $this->assertNotNull($user->checkout_token_hash);
        $this->assertNotSame($token, $user->checkout_token_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $user->checkout_token_hash);
        $this->assertSame($this->checkoutDigest($token), $user->checkout_token_hash);
        $this->assertTrue($user->checkout_token_expires_at->between(now()->addMinutes(19), now()->addMinutes(20)));
    }

    public function test_checkout_requires_valid_continuation_token_and_rejects_expired_or_cross_user_tokens(): void
    {
        config()->set('services.stripe.fake_checkout', true);
        $this->storePlans();

        $user = $this->user('checkout-user@test.com', emailVerified: true);
        $other = $this->user('other-checkout-user@test.com', emailVerified: true);

        $validToken = app(CheckoutContinuationTokenService::class)->issueForUser($user);
        $otherToken = app(CheckoutContinuationTokenService::class)->issueForUser($other);

        $expired = $this->user('expired-checkout-user@test.com', emailVerified: true);
        $expiredToken = app(CheckoutContinuationTokenService::class)->issueForUser($expired);
        $expired->forceFill(['checkout_token_expires_at' => now()->subMinute()])->save();

        foreach ([
            ['email' => 'missing-checkout@test.com', 'checkout_token' => str_repeat('a', 64)],
            ['email' => $user->email],
            ['email' => $user->email, 'checkout_token' => str_repeat('b', 64)],
            ['email' => $expired->email, 'checkout_token' => $expiredToken],
            ['email' => $user->email, 'checkout_token' => $otherToken],
        ] as $payload) {
            $this->postJson('/api/v1/billing/checkout', array_merge([
                'subscription_plan' => 'monthly',
            ], $payload))
                ->assertStatus(202)
                ->assertJsonPath('message', 'Checkout request received. If eligible, checkout can continue.')
                ->assertJsonPath('data.next_step', 'check_registration_status');
        }

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'checkout_token' => $validToken,
            'subscription_plan' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'fake')
            ->assertJsonPath('data.next_step', 'completed');

        $user->refresh();
        $this->assertNotNull($user->checkout_token_hash);
        $this->assertNotNull($user->checkout_token_expires_at);
        $this->assertNotNull($user->checkout_token_consumed_at);
        $this->assertNotNull($user->checkout_replay_message);
        $this->assertNotNull($user->checkout_replay_data);

        Cache::flush();

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'checkout_token' => $validToken,
            'subscription_plan' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'fake')
            ->assertJsonPath('data.next_step', 'completed');

        $this->assertSame(1, DB::table('subscriptions')->where('user_id', $user->id)->count());
    }

    public function test_consumed_checkout_token_replays_without_running_checkout_again(): void
    {
        $user = $this->user('atomic-replay@test.com', emailVerified: true);
        $token = app(CheckoutContinuationTokenService::class)->issueForUser($user);
        $service = app(CheckoutContinuationTokenService::class);

        $first = $service->reserveForCheckout($user->email, $token);

        $this->assertTrue($first['reserved']);
        $this->assertNotNull($user->refresh()->checkout_token_consumed_at);

        $inProgressRetry = $service->reserveForCheckout($user->email, $token);
        $this->assertNull($inProgressRetry);

        $service->storeCheckoutResponse($first['user'], 'Checkout session created successfully.', [
            'mode' => 'fake',
            'next_step' => 'completed',
            'user_id' => $user->id,
            'session_id' => 'checkout_first',
        ]);

        $replay = $service->reserveForCheckout($user->email, $token);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('Checkout session created successfully.', $replay['message']);
        $this->assertSame('checkout_first', $replay['data']['session_id']);
    }

    public function test_user_serialization_hides_internal_checkout_state(): void
    {
        $user = $this->user('hidden-checkout-state@test.com', emailVerified: true);
        app(CheckoutContinuationTokenService::class)->issueForUser($user);

        $user->refresh()->forceFill([
            'checkout_token_consumed_at' => now(),
            'checkout_replay_message' => 'Checkout session created successfully.',
            'checkout_replay_data' => ['session_id' => 'cs_hidden'],
        ])->save();

        $serialized = $user->refresh()->toArray();

        foreach ([
            'checkout_token_hash',
            'checkout_token_expires_at',
            'checkout_token_consumed_at',
            'checkout_replay_message',
            'checkout_replay_data',
        ] as $field) {
            $this->assertArrayNotHasKey($field, $serialized);
        }
    }

    public function test_unverified_registration_cannot_checkout_even_with_token_state(): void
    {
        config()->set('services.stripe.fake_checkout', true);
        $this->storePlans();

        $user = $this->user('unverified-token@test.com');
        $token = app(CheckoutContinuationTokenService::class)->issueForUser($user);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'checkout_token' => $token,
            'subscription_plan' => 'monthly',
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.next_step', 'check_registration_status');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_sensitive_otp_verification_endpoint_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/v1/auth/verify-otp', [
                    'email' => 'throttle-otp@test.com',
                    'otp' => '123456',
                ])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/auth/verify-otp', [
                'email' => 'throttle-otp@test.com',
                'otp' => '123456',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_sensitive_checkout_endpoint_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
                ->postJson('/api/v1/billing/checkout', [
                    'email' => 'throttle-checkout@test.com',
                    'subscription_plan' => 'monthly',
                ])
                ->assertStatus(202);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->postJson('/api/v1/billing/checkout', [
                'email' => 'throttle-checkout@test.com',
                'subscription_plan' => 'monthly',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('platform_settings');
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
            $table->string('checkout_token_hash')->nullable();
            $table->timestamp('checkout_token_expires_at')->nullable();
            $table->timestamp('checkout_token_consumed_at')->nullable();
            $table->string('checkout_replay_message')->nullable();
            $table->json('checkout_replay_data')->nullable();
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

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('group');
            $table->string('type');
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    private function user(string $email, bool $emailVerified = false): User
    {
        $roleId = DB::table('roles')->where('name', 'user')->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => 'Enumeration Test User',
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => $emailVerified ? now() : null,
            'is_active' => $emailVerified,
            'first_login_mfa_completed_at' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    private function storePlans(): void
    {
        DB::table('platform_settings')->insert([
            'key' => 'subscription_plans.plans',
            'value' => json_encode(['value' => [[
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => '7.00',
                'stripe_price_id' => 'price_test_monthly',
                'enabled' => true,
                'founding_member_cap' => null,
            ]]]),
            'group' => 'subscription_plans',
            'type' => 'array',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function checkoutDigest(string $token): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded !== false && $decoded !== '' ? $decoded : $key;
        }

        return hash_hmac('sha256', $token, $key);
    }
}
