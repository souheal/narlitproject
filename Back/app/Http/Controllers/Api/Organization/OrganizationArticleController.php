<?php

namespace App\Http\Controllers\Api\Organization;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Organization\OrganizationArticleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationArticleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationArticleService $articleService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        return $this->success('Articles retrieved.', [
            'articles' => $this->articleService->paginate($organization, $perPage, $page),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:280'],
            'content' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
            'read_time' => ['nullable', 'integer', 'min:1', 'max:120'],
            'read_time_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'status' => ['nullable', 'string', 'in:draft,pending_review'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:4096'],
        ]);

        // Accept both `body` (frontend) and `content` (spec).
        $data['content'] = $data['content'] ?? $data['body'] ?? null;
        if (! is_string($data['content']) || trim($data['content']) === '') {
            throw new ApiException('Article content is required.', 422);
        }

        // Accept both `read_time` and `read_time_minutes`.
        if (! isset($data['read_time']) && isset($data['read_time_minutes'])) {
            $data['read_time'] = $data['read_time_minutes'];
        }

        $article = $this->articleService->create(
            $organization,
            $data,
            $request->file('cover_image'),
        );

        return $this->success('Article submitted.', [
            'article' => $this->articleService->transform($article),
        ], 201);
    }

    protected function currentOrganization(Request $request)
    {
        $organization = $request->user()?->organizationProfile;

        if ($organization === null) {
            throw new ApiException('No organization profile found for this user.', 403);
        }

        return $organization;
    }
}
