<?php

namespace App\Support;

use RuntimeException;

class ProductionSecurityGuard
{
    public function validate(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if ((bool) config('app.debug')) {
            throw new RuntimeException('Unsafe production configuration: APP_DEBUG must be disabled.');
        }

        if ((bool) config('services.stripe.fake_checkout')) {
            throw new RuntimeException('Unsafe production configuration: Stripe fake checkout must be disabled.');
        }

        if (config('session.secure') !== true) {
            throw new RuntimeException('Unsafe production configuration: secure session cookies must be enabled.');
        }

        if (config('session.encrypt') !== true) {
            throw new RuntimeException('Unsafe production configuration: session encryption must be enabled.');
        }
    }
}
