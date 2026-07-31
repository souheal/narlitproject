<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Admin\PlatformSettingsService;
use App\Services\Member\MemberSubscriptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberSubscriptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        Cache::flush();
    }

    public function test_plan_catalogue_returns_enabled_plans(): void
    {
        $this->storePlans([
            $this->plan('monthly', 'Monthly', 'monthly', '7.00', 'price_monthly', true),
        ]);

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();

        $this->assertSame([
            [
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => '7.00',
                'stripe_price_id' => 'price_monthly',
                'enabled' => true,
            ],
        ], $catalogue);
    }

    public function test_disabled_plans_are_excluded(): void
    {
        $this->storePlans([
            $this->plan('monthly', enabled: true),
            $this->plan('yearly', 'Yearly', 'yearly', '96.00', 'price_yearly', false),
        ]);

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();

        $this->assertCount(1, $catalogue);
        $this->assertSame('monthly', $catalogue[0]['key']);
    }

    public function test_updated_platform_settings_are_reflected_immediately(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        $settings = app(PlatformSettingsService::class);

        $this->storePlans([
            $this->plan('monthly', 'Monthly', 'monthly', '7.00', 'price_monthly', true),
        ]);

        $this->assertSame('7.00', app(MemberSubscriptionService::class)->planCatalogue()[0]['display_price']);

        $settings->update('subscription_plans', [
            'plans' => [
                $this->plan('monthly', 'Monthly Plus', 'monthly', '11.00', 'price_new_monthly', true),
                $this->plan('yearly', 'Yearly', 'yearly', '120.00', 'price_yearly', false),
            ],
        ], $admin, Request::create('/api/v1/admin/settings/subscription_plans', 'PUT'));

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();

        $this->assertCount(1, $catalogue);
        $this->assertSame('Monthly Plus', $catalogue[0]['name']);
        $this->assertSame('11.00', $catalogue[0]['display_price']);
        $this->assertSame('price_new_monthly', $catalogue[0]['stripe_price_id']);
    }

    public function test_old_stripe_config_plan_prices_are_not_used(): void
    {
        config()->set('services.stripe.monthly_price', '999.99');
        config()->set('services.stripe.yearly_price', '9999.99');
        config()->set('services.stripe.monthly_amount', 99999);
        config()->set('services.stripe.yearly_amount', 999999);

        $this->storePlans([
            $this->plan('monthly', 'Monthly', 'monthly', '8.25', 'price_settings_monthly', true),
        ]);

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();

        $this->assertSame('8.25', $catalogue[0]['display_price']);
        $this->assertSame('price_settings_monthly', $catalogue[0]['stripe_price_id']);
    }

    public function test_missing_or_malformed_plans_are_handled_safely(): void
    {
        PlatformSetting::query()->create([
            'key' => 'subscription_plans.plans',
            'value' => ['value' => null],
            'group' => 'subscription_plans',
            'type' => 'array',
            'is_public' => false,
        ]);

        $this->assertSame([], app(MemberSubscriptionService::class)->planCatalogue());

        Cache::flush();
        PlatformSetting::query()->update([
            'value' => ['value' => [
                ['enabled' => true, 'key' => 'missing_fields'],
                $this->plan('bad_secret', enabled: true, stripePriceId: 'price_valid'),
                array_merge($this->plan('bad_stripe_id', enabled: true), ['stripe_price_id' => ['bad']]),
            ]],
        ]);

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();

        $this->assertCount(1, $catalogue);
        $this->assertSame('bad_secret', $catalogue[0]['key']);
    }

    public function test_existing_member_dashboard_response_remains_valid(): void
    {
        $user = $this->userWithRole('subscriber', 'member@test.com');
        Sanctum::actingAs($user);

        DB::table('subscriptions')->insert([
            'public_id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'plan' => 'monthly',
            'amount' => '7.00',
            'currency' => 'USD',
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.member.subscription_plan', 'monthly')
            ->assertJsonPath('data.dashboard.subscription_breakdown.subscription_amount', '7.00');
    }

    public function test_no_stripe_secret_values_are_exposed(): void
    {
        $this->storePlans([
            array_merge($this->plan('monthly', enabled: true), [
                'stripe_secret' => 'sk_test_secret',
                'client_secret' => 'secret_value',
            ]),
        ]);

        $catalogue = app(MemberSubscriptionService::class)->planCatalogue();
        $encoded = json_encode($catalogue);

        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('sk_test_secret', $encoded);
        $this->assertStringNotContainsString('secret_value', $encoded);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('impact_wallets');
        Schema::dropIfExists('impact_transactions');
        Schema::dropIfExists('article_reads');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('platform_settings');
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

        Schema::create('organization_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('organization_name');
            $table->string('website')->nullable();
            $table->string('landline')->nullable();
            $table->string('tax_id')->unique();
            $table->string('certificate_file')->nullable();
            $table->boolean('irs_verified')->default(false);
            $table->string('verification_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('stripe_connect_account_id')->nullable();
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('plan');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('incomplete');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('category', 120)->nullable();
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('featured_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->smallInteger('read_time')->nullable();
            $table->bigInteger('total_reads')->default(0);
            $table->bigInteger('total_unique_reads')->default(0);
            $table->bigInteger('total_reading_seconds')->default(0);
            $table->bigInteger('total_points_generated')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles');
            $table->foreignId('user_id')->constrained('users');
            $table->smallInteger('read_percent')->default(0);
            $table->integer('reading_seconds')->default(0);
            $table->integer('points_earned')->default(0);
            $table->boolean('counted_for_payout')->default(false);
            $table->string('session_id', 128)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('impact_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->foreignId('article_id')->nullable()->constrained('articles');
            $table->foreignId('payment_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->bigInteger('points_generated')->default(0);
            $table->date('transaction_month');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('impact_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->decimal('total_impact_amount', 12, 2)->default(0);
            $table->bigInteger('total_articles_read')->default(0);
            $table->bigInteger('total_points')->default(0);
            $table->bigInteger('total_organizations_supported')->default(0);
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

        $user = User::create([
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

        $user->createToken('test-token');

        return $user;
    }
}
