<?php

namespace App\Notifications\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordResetOtpNotification extends Notification implements ShouldQueue
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
            ->subject('NarLit Password Reset Code')
            ->greeting('Password reset request')
            ->line("Your NarLit password reset code is {$this->otp}.")
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
