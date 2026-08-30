<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
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

    public function test_development_http_requests_are_not_forced_to_https(): void
    {
        URL::forceScheme(null);
        config(['app.url' => 'http://localhost']);

        $this->assertSame('http://localhost/api/v1/stripe/webhook', URL::to('/api/v1/stripe/webhook'));

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_production_boot_forces_secure_url_generation(): void
    {
        $previousEnvironment = app()->environment();
        $previousConfig = [
            'app.debug' => config('app.debug'),
            'app.url' => config('app.url'),
            'services.stripe.fake_checkout' => config('services.stripe.fake_checkout'),
            'session.secure' => config('session.secure'),
            'session.encrypt' => config('session.encrypt'),
        ];

        try {
            URL::forceScheme(null);
            app()->detectEnvironment(fn (): string => 'production');
            config([
                'app.debug' => false,
                'app.url' => 'http://api.narlit.com',
                'services.stripe.fake_checkout' => false,
                'session.secure' => true,
                'session.encrypt' => true,
            ]);

            (new AppServiceProvider(app()))->boot();

            $this->assertStringStartsWith('https://', URL::to('/api/v1/stripe/webhook'));
            $this->assertStringEndsWith('/api/v1/stripe/webhook', URL::to('/api/v1/stripe/webhook'));
        } finally {
            URL::forceScheme(null);
            app()->detectEnvironment(fn (): string => $previousEnvironment);
            config($previousConfig);
        }
    }

    public function test_untrusted_forwarded_proto_does_not_enable_hsts(): void
    {
        $previousEnvironment = app()->environment();

        try {
            TrustProxies::flushState();
            app()->detectEnvironment(fn (): string => 'production');

            $this->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.44',
                'SERVER_PORT' => 80,
                'HTTPS' => 'off',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
                ->getJson('http://localhost/api/v1/subscription/plans')
                ->assertOk()
                ->assertHeaderMissing('Strict-Transport-Security');
        } finally {
            TrustProxies::flushState();
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }

    public function test_trusted_proxy_forwarded_proto_enables_production_hsts(): void
    {
        $previousEnvironment = app()->environment();

        try {
            TrustProxies::at('127.0.0.1');
            TrustProxies::withHeaders(
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
            );
            app()->detectEnvironment(fn (): string => 'production');

            $this->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
                'SERVER_PORT' => 80,
                'HTTPS' => 'off',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
                ->getJson('http://localhost/api/v1/subscription/plans')
                ->assertOk()
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        } finally {
            TrustProxies::flushState();
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
