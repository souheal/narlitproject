<?php

namespace App\Http\Controllers\Api\Member;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Impact\UserImpactDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberArticleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserImpactDashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));

        return $this->success('Articles retrieved.', $this->dashboardService->articles($request->user(), $perPage));
    }

    public function markRead(Request $request, string $publicId): JsonResponse
    {
        $data = $request->validate([
            'read_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'reading_seconds' => ['sometimes', 'integer', 'min:0'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'device_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);

        $article = Article::query()
            ->where('public_id', $publicId)
            ->where('status', 'published')
            ->first();

        if ($article === null) {
            throw new ApiException('The requested article was not found.', 404);
        }

        $data['ip_address'] = $request->ip();
        $data['user_agent'] = (string) $request->userAgent();

        return $this->success('Article read recorded.', $this->dashboardService->recordRead(
            $request->user(),
            $article,
            $data,
        ), 201);
    }
}
