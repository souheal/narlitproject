<?php

namespace App\Services\Notifications;

class SmsService
{
    public function send(string $phone, string $message): void
    {
        if (app()->environment('local')) {
            return;
        }

        logger()->info('SMS provider is not configured.', [
            'phone' => $phone,
        ]);
    }
}
