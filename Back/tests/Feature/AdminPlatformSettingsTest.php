<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));
        $this->createTestSchema();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_read_defaults_without_exposing_secrets(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com'));

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.settings.impact_split.nonprofit_percentage', 33)
            ->assertJsonPath('data.settings.payout.automatic_execution_enabled', false)
            ->assertJsonPath('data.settings.email.sender_address', 'no-reply@narlit.com')
            ->assertJsonMissingPath('data.settings.stripe.secret')
            ->assertJsonMissingPath('data.settings.database.password')
            ->assertJsonMissingPath('data.settings.app_key');
    }

    public function test_admin_can_update_impact_split_and_cache_is_invalidated_with_audit_log(): void
    {
        $admin = $this->userWithRole('admin', 'admin@test.com');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/settings/impact_split')
            ->assertOk()
            ->assertJsonPath('data.settings.nonprofit_percentage', 33);

        $this->assertTrue(Cache::has('platform_settings:impact_split'));

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 40,
            'operations_percentage' => 30,
            'growth_percentage' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.nonprofit_percentage', 40)
            ->assertJsonPath('data.settings.operations_percentage', 30)
            ->assertJsonPath('data.settings.growth_percentage', 30);

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'impact_split.nonprofit_percentage',
            'group' => 'impact_split',
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'platform_settings.updated',
            'entity_type' => 'platform_settings',
            'entity_id' => 'impact_split',
        ]);

        $this->getJson('/api/v1/admin/settings/impact_split')
            ->assertOk()
            ->assertJsonPath('data.settings.nonprofit_percentage', 40);
    }

    public function test_validation_rejects_invalid_percentages_prices_dates_and_stripe_price_ids(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com'));

        $this->putJson('/api/v1/admin/settings/impact_split', [
            'nonprofit_percentage' => 50,
            'operations_percentage' => 30,
            'growth_percentage' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total']);

        $this->putJson('/api/v1/admin/settings/subscription_plans', [
            'plans' => [[
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => -1,
                'stripe_price_id' => 'bad_price',
                'enabled' => true,
                'founding_member_cap' => null,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plans.0.display_price', 'plans.0.stripe_price_id']);

        $this->putJson('/api/v1/admin/settings/payout', [
            'minimum_payout_amount' => '25.00',
            'payout_day_of_month' => 31,
            'automatic_execution_enabled' => false,
            'retry_attempts' => 3,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payout_day_of_month']);
    }

    public function test_admin_can_update_subscription_plans_and_email_settings(): void
    {
        Sanctum::actingAs($this->userWithRole('admin', 'admin@test.com'));

        $this->putJson('/api/v1/admin/settings/subscription_plans', [
            'plans' => [[
                'key' => 'founding',
                'name' => 'Founding Member',
                'billing_interval' => 'yearly',
                'display_price' => '120.00',
                'stripe_price_id' => 'price_123abc',
                'enabled' => true,
                'founding_member_cap' => 500,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.plans.0.key', 'founding')
            ->assertJsonPath('data.settings.plans.0.stripe_price_id', 'price_123abc');

        $this->putJson('/api/v1/admin/settings/email', [
            'sender_name' => 'NarLit Support',
            'sender_address' => 'no-reply@narlit.com',
            'support_email' => 'help@narlit.com',
            'transactional_emails' => [
                'email_otp' => true,
                'password_reset' => true,
                'phone_mfa' => false,
                'subscription_receipts' => true,
                'organization_review_updates' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.support_email', 'help@narlit.com')
            ->assertJsonPath('data.settings.transactional_emails.phone_mfa', false);
    }

    public function test_settings_require_admin_access(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();

        Sanctum::actingAs($this->userWithRole('subscriber', 'member@test.com'));

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings/impact_split', [])->assertForbidden();
    }

    private function createTestSchema(): void
    {
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
