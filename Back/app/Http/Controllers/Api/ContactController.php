<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:60'],
        ]);

        $ticket = SupportTicket::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $data['name'],
            'guest_email' => $request->user() ? null : $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'category' => $data['reason'] ?? 'general',
            'status' => 'open',
        ]);

        return $this->success('Message received. We will be in touch soon.', [
            'ticket' => [
                'public_id' => $ticket->public_id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
