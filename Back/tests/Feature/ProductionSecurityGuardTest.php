<?php

namespace Tests\Feature;

use App\Support\ProductionSecurityGuard;
use RuntimeException;
use Tests\TestCase;

class ProductionSecurityGuardTest extends TestCase
{
    public function test_development_and_testing_environments_are_unaffected(): void
    {
        $previousEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'local');
            config()->set('app.debug', true);
            config()->set('services.stripe.fake_checkout', true);
            config()->set('session.secure', false);
            config()->set('session.encrypt', false);

            app(ProductionSecurityGuard::class)->validate();

            $this->assertTrue(true);
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }

    public function test_unsafe_simulated_production_configuration_is_rejected(): void
    {
        $previousEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'production');
            config()->set('app.debug', true);
            config()->set('services.stripe.fake_checkout', false);
            config()->set('session.secure', true);
            config()->set('session.encrypt', true);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsafe production configuration: APP_DEBUG must be disabled.');

            app(ProductionSecurityGuard::class)->validate();
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }

    public function test_safe_simulated_production_configuration_passes(): void
    {
        $previousEnvironment = app()->environment();

        try {
            app()->detectEnvironment(fn (): string => 'production');
            config()->set('app.debug', false);
            config()->set('services.stripe.fake_checkout', false);
            config()->set('session.secure', true);
            config()->set('session.encrypt', true);

            app(ProductionSecurityGuard::class)->validate();

            $this->assertTrue(true);
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }
}
