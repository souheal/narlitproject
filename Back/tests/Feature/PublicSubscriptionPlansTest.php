<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicSubscriptionPlansTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        Cache::flush();
    }

    public function test_guest_can_access_enabled_subscription_plans(): void
    {
        $this->storePlans([
            $this->plan('monthly', enabled: true),
            $this->plan('yearly', enabled: false),
        ]);

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription plans retrieved successfully.')
            ->assertJsonPath('data.plans.0.key', 'monthly')
            ->assertJsonMissingPath('data.plans.1');
    }

    public function test_authenticated_user_can_access_enabled_subscription_plans(): void
    {
        $this->storePlans([
            $this->plan('monthly', enabled: true),
        ]);

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com'));

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('data.plans.0.name', 'Monthly');
    }

    public function test_enabled_plans_are_returned_in_configured_order_and_disabled_plans_are_excluded(): void
    {
        $this->storePlans([
            $this->plan('yearly', 'Yearly', 'yearly', '96.00', 'price_yearly', true),
            $this->plan('disabled', 'Disabled', 'monthly', '1.00', 'price_disabled', false),
            $this->plan('monthly', 'Monthly', 'monthly', '7.00', 'price_monthly', true),
        ]);

        $response = $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data.plans');

        $this->assertSame(['yearly', 'monthly'], collect($response->json('data.plans'))->pluck('key')->all());
    }

    public function test_response_contains_only_allowed_public_fields(): void
    {
        $this->storePlans([
            array_merge($this->plan('monthly', enabled: true), [
                'founding_member_cap' => 500,
                'internal_setting_id' => 123,
                'updated_by' => 99,
                'stripe_secret_key' => 'sk_test_secret',
                'metadata' => ['admin_only' => true],
            ]),
        ]);

        $response = $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('data.plans.0.stripe_price_id', 'price_monthly');

        $plan = $response->json('data.plans.0');

        $this->assertSame([
            'key',
            'name',
            'billing_interval',
            'display_price',
            'stripe_price_id',
        ], array_keys($plan));
        $this->assertStringNotContainsString('sk_test_secret', $response->getContent());
        $this->assertStringNotContainsString('internal_setting_id', $response->getContent());
        $this->assertStringNotContainsString('updated_by', $response->getContent());
        $this->assertStringNotContainsString('metadata', $response->getContent());
    }

    public function test_empty_enabled_plan_list_returns_ok_with_empty_array(): void
    {
        $this->storePlans([
            $this->plan('monthly', enabled: false),
            $this->plan('yearly', enabled: false),
        ]);

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertExactJson([
                'message' => 'Subscription plans retrieved successfully.',
                'data' => [
                    'plans' => [],
                ],
            ]);
    }

    public function test_malformed_stored_settings_are_handled_safely(): void
    {
        $this->storePlans([
            'not-a-plan',
            ['enabled' => true, 'key' => 'missing_fields'],
            $this->plan('valid', 'Valid', 'monthly', '9.00', 'price_valid', true),
            array_merge($this->plan('bad_secret', enabled: true), ['stripe_price_id' => ['bad']]),
        ]);

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data.plans')
            ->assertJsonPath('data.plans.0.key', 'valid');
    }

    public function test_no_internal_setting_metadata_is_exposed(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');

        PlatformSetting::query()->create([
            'key' => 'subscription_plans.plans',
            'value' => ['value' => [$this->plan('monthly', enabled: true)]],
            'group' => 'subscription_plans',
            'type' => 'array',
            'is_public' => false,
            'updated_by' => $admin->id,
        ]);

        $response = $this->getJson('/api/v1/subscription/plans')
            ->assertOk();

        $content = $response->getContent();

        $this->assertStringNotContainsString((string) PlatformSetting::query()->value('id'), $content);
        $this->assertStringNotContainsString('is_public', $content);
        $this->assertStringNotContainsString('updated_by', $content);
        $this->assertStringNotContainsString('subscription_plans.plans', $content);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('platform_settings');
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

    private function plan(
        string $key,
        string $name = 'Monthly',
        string $billingInterval = 'monthly',
        string $displayPrice = '7.00',
        string $stripePriceId = 'price_monthly',
        bool $enabled = true,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'billing_interval' => $billingInterval,
            'display_price' => $displayPrice,
            'stripe_price_id' => $stripePriceId,
            'enabled' => $enabled,
            'founding_member_cap' => null,
        ];
    }

    private function userWithRole(string $role, string $email): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
