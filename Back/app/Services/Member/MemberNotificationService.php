<?php

namespace App\Services\Member;

use App\Exceptions\ApiException;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberNotificationService
{
    public function paginate(User $user, string $filter = 'all', int $perPage = 20): array
    {
        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(fn (Notification $n): array => $this->present($n));

        return [
            'notifications' => $paginator,
        ];
    }

    public function unreadCount(User $user): array
    {
        return [
            'count' => Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'unread_count' => Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ];
    }

    public function markAllRead(User $user): array
    {
        $marked = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['marked' => (int) $marked];
    }

    public function markRead(User $user, string $publicId): array
    {
        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->where('public_id', $publicId)
            ->first();

        if ($notification === null) {
            throw new ApiException('Notification not found.', 404);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return ['notification' => $this->present($notification->refresh())];
    }

    public function present(Notification $n): array
    {
        return [
            'public_id' => $n->public_id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'action_label' => $n->action_label,
            'icon' => $n->icon,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
