<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Admin\PlatformSettingsService;
use App\Services\Auth\RegistrationService;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class StripeCheckoutPlatformSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        Cache::flush();
        config()->set('services.stripe.fake_checkout', false);
        config()->set('services.stripe.success_url', 'https://app.test/success');
        config()->set('services.stripe.cancel_url', 'https://app.test/cancel');
    }

    public function test_enabled_plan_with_valid_stripe_price_id_creates_checkout_using_price_field(): void
    {
        $user = $this->verifiedUser();
        $fake = $this->fakeCheckoutService();
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', true)]);

        $this->app->instance(StripeCheckoutService::class, $fake);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('data.checkout_session_id', 'cs_test_123')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session');

        $this->assertSame([[
            'price' => 'price_settings_monthly',
            'quantity' => 1,
        ]], $fake->payload['line_items']);
        $this->assertArrayNotHasKey('price_data', $fake->payload['line_items'][0]);
        $this->assertStringStartsWith('checkout:'.$user->public_id.':monthly:', $fake->idempotencyKey);
    }

    public function test_disabled_plan_is_rejected(): void
    {
        $user = $this->verifiedUser();
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', false)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected subscription plan is currently disabled.');
    }

    public function test_unknown_plan_is_rejected(): void
    {
        $user = $this->verifiedUser();
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'yearly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected subscription plan is unavailable.');
    }

    public function test_missing_or_invalid_stripe_price_id_is_rejected(): void
    {
        $user = $this->verifiedUser();
        $this->storePlans([$this->plan('monthly', null, true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected subscription plan is not configured for payment.');

        Cache::flush();
        PlatformSetting::query()->delete();
        $this->storePlans([$this->plan('monthly', 'bad_price', true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected subscription plan is not configured for payment.');
    }

    public function test_updated_platform_settings_price_id_is_used_immediately(): void
    {
        $user = $this->verifiedUser();
        $fake = $this->fakeCheckoutService();
        $this->app->instance(StripeCheckoutService::class, $fake);

        $this->storePlans([$this->plan('monthly', 'price_old_monthly', true)]);
        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])->assertOk();
        $this->assertSame('price_old_monthly', $fake->payload['line_items'][0]['price']);

        Cache::flush();
        PlatformSetting::query()->update([
            'value' => ['value' => [$this->plan('monthly', 'price_new_monthly', true)]],
        ]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])->assertOk();
        $this->assertSame('price_new_monthly', $fake->payload['line_items'][0]['price']);
    }

    public function test_user_is_not_activated_before_webhook_confirmation(): void
    {
        $user = $this->verifiedUser(active: false);
        $fake = $this->fakeCheckoutService();
        $this->app->instance(StripeCheckoutService::class, $fake);
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])->assertOk();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_stripe_exceptions_are_safely_handled(): void
    {
        $user = $this->verifiedUser();
        $fake = $this->fakeCheckoutService(failSession: true);
        $this->app->instance(StripeCheckoutService::class, $fake);
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Unable to create the checkout session. Please try again.');
    }

    public function test_metadata_contains_only_approved_fields_and_preserves_webhook_plan_compatibility(): void
    {
        $user = $this->verifiedUser();
        $fake = $this->fakeCheckoutService();
        $this->app->instance(StripeCheckoutService::class, $fake);
        $this->storePlans([$this->plan('monthly', 'price_settings_monthly', true)]);

        $this->postJson('/api/v1/billing/checkout', [
            'email' => $user->email,
            'subscription_plan' => 'monthly',
        ])->assertOk();

        $this->assertSame([
            'user_id' => (string) $user->id,
            'user_public_id' => $user->public_id,
            'selected_plan_key' => 'monthly',
            'plan' => 'monthly',
        ], $fake->payload['metadata']);
        $this->assertSame($fake->payload['metadata'], $fake->payload['subscription_data']['metadata']);

        $encoded = json_encode($fake->payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('Password123!', $encoded);
        $this->assertStringNotContainsString('otp', strtolower($encoded));
        $this->assertStringNotContainsString('access_token', $encoded);
        $this->assertStringNotContainsString('sk_test', $encoded);
    }

    private function fakeCheckoutService(bool $failSession = false): FakeStripeCheckoutService
    {
        return new FakeStripeCheckoutService(
            app(RegistrationService::class),
            app(SubscriptionService::class),
            app(PlatformSettingsService::class),
            $failSession,
        );
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
            $table->boolean('is_active')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
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

    private function storePlans(array $plans): void
    {
        PlatformSetting::query()->create([
            'key' => 'subscription_plans.plans',
            'value' => ['value' => $plans],
            'group' => 'subscription_plans',
            'type' => 'array',
            'is_public' => false,
        ]);

        Cache::flush();
    }

    private function plan(string $key, ?string $stripePriceId, bool $enabled): array
    {
        return [
            'key' => $key,
            'name' => str($key)->headline()->toString(),
            'billing_interval' => $key === 'yearly' ? 'yearly' : 'monthly',
            'display_price' => $key === 'yearly' ? '96.00' : '7.00',
            'stripe_price_id' => $stripePriceId,
            'enabled' => $enabled,
            'founding_member_cap' => null,
        ];
    }

    private function verifiedUser(bool $active = false): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'subscriber',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => 'Member User',
            'username' => 'member_user',
            'email' => 'member@test.com',
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => $active,
            'failed_login_attempts' => 0,
        ]);
    }
}

class FakeStripeCheckoutService extends StripeCheckoutService
{
    public array $payload = [];

    public string $idempotencyKey = '';

    public function __construct(
        RegistrationService $registrationService,
        SubscriptionService $subscriptionService,
        PlatformSettingsService $settings,
        protected bool $failSession = false,
    ) {
        parent::__construct($registrationService, $subscriptionService, $settings);
    }

    protected function createStripeCustomer(User $user): string
    {
        return 'cus_test_'.$user->id;
    }

    protected function createStripeCheckoutSession(array $payload, string $idempotencyKey): object
    {
        if ($this->failSession) {
            throw ApiConnectionException::factory('Stripe network failure');
        }

        $this->payload = $payload;
        $this->idempotencyKey = $idempotencyKey;

        return (object) [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.test/session',
            'expires_at' => 1800000000,
        ];
    }
}
