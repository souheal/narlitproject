<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        $this->storePlans();
        Cache::flush();
    }

    public function test_api_responses_include_security_headers_without_hsts_in_testing(): void
    {
        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertJsonPath('message', 'Subscription plans retrieved successfully.')
            ->assertJsonPath('data.plans.0.key', 'monthly');
    }

    public function test_hsts_is_present_for_production_https_requests(): void
    {
        $previousEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'production');

            $this->withServerVariables([
                'HTTPS' => 'on',
                'SERVER_PORT' => 443,
            ])
                ->getJson('https://localhost/api/v1/subscription/plans')
                ->assertOk()
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }

    public function test_unauthorized_error_responses_include_security_headers(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_validation_error_responses_include_security_headers(): void
    {
        $this->postJson('/api/v1/auth/verify-otp', [])
            ->assertStatus(422)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_throttle_error_responses_include_security_headers(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.33'])
                ->postJson('/api/v1/auth/verify-otp', [])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.33'])
            ->postJson('/api/v1/auth/verify-otp', [])
            ->assertStatus(429)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_exception_rendered_error_responses_include_security_headers(): void
    {
        Route::get('/api/v1/security-headers-test-exception', function (): void {
            throw new RuntimeException('Simulated test exception.');
        });

        $this->getJson('/api/v1/security-headers-test-exception')
            ->assertStatus(500)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('group');
            $table->string('type');
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function storePlans(): void
    {
        PlatformSetting::query()->create([
            'key' => 'subscription_plans.plans',
            'value' => ['value' => [[
                'key' => 'monthly',
                'name' => 'Monthly',
                'billing_interval' => 'monthly',
                'display_price' => '7.00',
                'stripe_price_id' => 'price_monthly',
                'enabled' => true,
                'founding_member_cap' => null,
            ]]],
            'group' => 'subscription_plans',
            'type' => 'array',
            'is_public' => false,
        ]);
    }
}
