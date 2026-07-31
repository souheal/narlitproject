<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberNotificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberNotificationService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'unread'], true)) {
            $filter = 'all';
        }
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        return $this->success('Notifications retrieved.', $this->service->paginate($request->user(), $filter, $perPage));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success('Unread notification count retrieved.', $this->service->unreadCount($request->user()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return $this->success('Notifications marked as read.', $this->service->markAllRead($request->user()));
    }

    public function markRead(Request $request, string $publicId): JsonResponse
    {
        return $this->success('Notification marked as read.', $this->service->markRead($request->user(), $publicId));
    }
}
