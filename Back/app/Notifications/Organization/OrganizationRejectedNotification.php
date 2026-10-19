<?php

namespace App\Notifications\Organization;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $organizationName)
    {
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on Your Organization Application — NarLit')
            ->view([
                'html' => 'emails.organization.review-decision',
                'text' => 'emails.organization.review-decision-text',
            ], [
                'organizationName' => $this->organizationName,
                'approved' => false,
            ]);
    }
}
