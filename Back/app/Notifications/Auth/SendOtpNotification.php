<?php

namespace App\Notifications\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $otp,
        protected CarbonImmutable $expiresAt,
    ) {
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NarLit Verification Code')
            ->greeting('NarLit account verification')
            ->line("Your NarLit OTP code is {$this->otp}.")
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this code, no further action is required.');
    }
}
