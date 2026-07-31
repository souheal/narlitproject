<?php

namespace App\Services\Member;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Str;

class MemberSupportService
{
    public function createTicket(User $user, array $data): array
    {
        $ticket = SupportTicket::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
            'category' => $data['category'] ?? null,
            'status' => 'open',
        ]);

        return ['ticket' => $this->present($ticket)];
    }

    protected function present(SupportTicket $ticket): array
    {
        return [
            'public_id' => $ticket->public_id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
