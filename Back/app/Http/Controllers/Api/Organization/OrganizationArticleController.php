<?php

namespace App\Http\Controllers\Api\Organization;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Organization\OrganizationArticleService;
use App\Support\ApiResponse;
use App\Support\ArticleMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uploads arrive as multipart/form-data. PHP only parses multipart bodies on
 * POST, so clients send edits as `POST /articles/{id}` with `_method=PATCH`.
 */
class OrganizationArticleController extends Controller
{
    use ApiResponse;

    protected const IMAGE_RULE = ['image', 'mimes:jpeg,png,webp,gif', 'max:4096'];

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
            'approval_required' => $this->articleService->approvalRequired(),
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
            'status' => ['nullable', 'string', 'in:draft,publish,pending_review'],
            'cover_image' => ['nullable', ...self::IMAGE_RULE],
            'images' => ['nullable', 'array', 'max:'.ArticleMedia::MAX_GALLERY_IMAGES],
            'images.*' => self::IMAGE_RULE,
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
            $request->file('images', []),
        );

        return $this->success($this->statusMessage($article), [
            'article' => $this->articleService->transformDetail($article),
        ], 201);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        return $this->success('Article retrieved.', [
            'article' => $this->articleService->transformDetail(
                $this->articleService->find($organization, $publicId),
            ),
        ]);
    }

    public function update(Request $request, string $publicId): JsonResponse
    {
        $organization = $this->currentOrganization($request);
        $article = $this->articleService->find($organization, $publicId);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:280'],
            'content' => ['sometimes', 'nullable', 'string'],
            'body' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'read_time' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'read_time_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'in:draft,publish,pending_review'],
            'cover_image' => ['sometimes', 'nullable', ...self::IMAGE_RULE],
            'remove_cover' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'nullable', 'array', 'max:'.ArticleMedia::MAX_GALLERY_IMAGES],
            'images.*' => self::IMAGE_RULE,
            'remove_images' => ['sometimes', 'nullable', 'array'],
            'remove_images.*' => ['string', 'max:64'],
        ]);

        // Same aliases as store(): `body` → `content`, `read_time_minutes` → `read_time`.
        if (! array_key_exists('content', $data) && array_key_exists('body', $data)) {
            $data['content'] = $data['body'];
        }
        unset($data['body']);

        if (! array_key_exists('read_time', $data) && array_key_exists('read_time_minutes', $data)) {
            $data['read_time'] = $data['read_time_minutes'];
        }
        unset($data['read_time_minutes']);

        $article = $this->articleService->update(
            $organization,
            $article,
            $data,
            $request->file('cover_image'),
            $request->file('images', []),
            (bool) ($data['remove_cover'] ?? false),
            array_values($data['remove_images'] ?? []),
        );

        return $this->success($this->statusMessage($article), [
            'article' => $this->articleService->transformDetail($article),
        ]);
    }

    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $organization = $this->currentOrganization($request);

        $this->articleService->delete($this->articleService->find($organization, $publicId));

        return $this->success('Article deleted.', [
            'deleted_public_id' => $publicId,
        ]);
    }

    protected function statusMessage(Article $article): string
    {
        return match ($article->status) {
            'published' => 'Article published.',
            'pending_review' => 'Article submitted for review.',
            default => 'Draft saved.',
        };
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
