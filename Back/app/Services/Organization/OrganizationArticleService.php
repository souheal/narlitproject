<?php

namespace App\Services\Organization;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Services\Admin\PlatformSettingsService;
use App\Support\ArticleMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationArticleService
{
    public function __construct(
        protected PlatformSettingsService $settings,
    ) {
    }

    public function paginate(OrganizationProfile $organization, int $perPage, int $page): array
    {
        $paginator = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (Article $article): array => $this->transform($article))
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Admin setting content.article_approval_required (on by default). When it
     * is off, an organization's "publish" goes live straight away.
     */
    public function approvalRequired(): bool
    {
        return (bool) ($this->settings->group('content')['article_approval_required'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function create(OrganizationProfile $organization, array $data, ?UploadedFile $coverImage = null, array $images = []): Article
    {
        $title = (string) $data['title'];

        if (count($images) > ArticleMedia::MAX_GALLERY_IMAGES) {
            throw new ApiException('An article can have at most '.ArticleMedia::MAX_GALLERY_IMAGES.' gallery images.', 422);
        }

        $metadata = [];

        if ($coverImage instanceof UploadedFile) {
            $metadata['cover_image_path'] = ArticleMedia::store($coverImage);
        }

        if ($images !== []) {
            $metadata['gallery'] = array_map(fn (UploadedFile $file): array => ArticleMedia::galleryItem($file), $images);
        }

        // No status means "publish", as it always has for this endpoint.
        $wantsPublish = $this->wantsPublish($data['status'] ?? 'publish');
        $status = $wantsPublish && $organization->verification_status === 'approved'
            ? $this->publishTargetStatus()
            : 'draft';

        $article = Article::create([
            'public_id' => (string) Str::uuid(),
            'organization_profile_id' => $organization->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.substr((string) Str::uuid(), 0, 8),
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'read_time' => $data['read_time'] ?? null,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);

        return $article->fresh();
    }

    public function find(OrganizationProfile $organization, string $publicId): Article
    {
        $article = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->where('public_id', $publicId)
            ->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        return $article;
    }

    /**
     * Edit an article in any state. `status` decides what happens next:
     *   draft          keep it private (withdraws a pending one, takes a live one down)
     *   publish        go live — straight away, or via the admin queue when approval is required
     *   (omitted)      keep the current state, except that changing a live article
     *                  while approval is required sends it back to the admin queue
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     * @param  list<string>  $removeImageIds
     */
    public function update(
        OrganizationProfile $organization,
        Article $article,
        array $data,
        ?UploadedFile $coverImage = null,
        array $images = [],
        bool $removeCover = false,
        array $removeImageIds = [],
    ): Article {
        $fields = array_intersect_key($data, array_flip(['title', 'excerpt', 'content', 'category', 'read_time']));

        foreach (['title', 'content'] as $required) {
            if (array_key_exists($required, $fields) && trim((string) $fields[$required]) === '') {
                throw new ApiException("Article {$required} cannot be empty.", 422);
            }
        }

        $metadata = is_array($article->metadata) ? $article->metadata : [];
        $gallery = ArticleMedia::gallery($article);
        $kept = array_values(array_filter($gallery, fn (array $item): bool => ! in_array($item['id'], $removeImageIds, true)));

        if (count($kept) + count($images) > ArticleMedia::MAX_GALLERY_IMAGES) {
            throw new ApiException('An article can have at most '.ArticleMedia::MAX_GALLERY_IMAGES.' gallery images.', 422);
        }

        $requested = $data['status'] ?? null;
        $wantsPublish = $this->wantsPublish($requested);

        if ($wantsPublish && $organization->verification_status !== 'approved') {
            throw new ApiException('Your organization must be verified before publishing articles.', 403);
        }

        $filesToDelete = [];

        if ($removeCover || $coverImage instanceof UploadedFile) {
            $filesToDelete[] = $metadata['cover_image_path'] ?? null;
            unset($metadata['cover_image_path']);
        }

        if ($coverImage instanceof UploadedFile) {
            $metadata['cover_image_path'] = ArticleMedia::store($coverImage);
        }

        foreach ($gallery as $item) {
            if (in_array($item['id'], $removeImageIds, true)) {
                $filesToDelete[] = $item['path'];
            }
        }

        foreach ($images as $file) {
            $kept[] = ArticleMedia::galleryItem($file);
        }

        if ($kept === []) {
            unset($metadata['gallery']);
        } else {
            $metadata['gallery'] = $kept;
        }

        $article->fill($fields);
        $contentChanged = $article->isDirty(['title', 'excerpt', 'content', 'category'])
            || $coverImage !== null || $removeCover || $images !== [] || $removeImageIds !== [];

        $fromStatus = $article->status;
        $toStatus = match (true) {
            $requested === 'draft' => 'draft',
            $wantsPublish && $fromStatus === 'published' && ! ($contentChanged && $this->approvalRequired()) => 'published',
            $wantsPublish => $this->publishTargetStatus(),
            $fromStatus === 'published' && $contentChanged && $this->approvalRequired() => 'pending_review',
            default => $fromStatus,
        };

        if ($toStatus !== $fromStatus && in_array($toStatus, ['pending_review', 'published'], true)) {
            if ($fromStatus === 'rejected' || ($fromStatus === 'draft' && $article->rejection_reason !== null)) {
                $metadata['resubmitted_at'] = now()->toIso8601String();
                $metadata['resubmission_count'] = (int) ($metadata['resubmission_count'] ?? 0) + 1;
            }

            if ($fromStatus === 'published') {
                $metadata['edited_after_publish_at'] = now()->toIso8601String();
            }

            $article->rejection_reason = null;
        }

        if ($toStatus === 'published') {
            $article->published_at = $article->published_at ?? now();
        }

        if ($toStatus !== 'published') {
            $article->featured_at = null;
        }

        $article->status = $toStatus;
        $article->metadata = $metadata !== [] ? $metadata : null;
        $article->save();

        // Only drop old files once the row no longer points at them.
        foreach ($filesToDelete as $path) {
            ArticleMedia::delete($path);
        }

        return $article->fresh();
    }

    /**
     * Soft delete, so an admin can still restore it from the archive. A live
     * article disappears for readers immediately.
     */
    public function delete(Article $article): void
    {
        DB::transaction(function () use ($article): void {
            $metadata = is_array($article->metadata) ? $article->metadata : [];
            $metadata['deleted_by_organization_at'] = now()->toIso8601String();
            $metadata['status_before_delete'] = $article->status;

            $article->forceFill(['metadata' => $metadata, 'featured_at' => null])->save();
            $article->delete();
        });
    }

    public function transformDetail(Article $article): array
    {
        $changeRequest = $article->metadata['last_change_request'] ?? null;

        return $this->transform($article) + [
            'content' => $article->content,
            'updated_at' => $article->updated_at?->toIso8601String(),
            'can_edit' => true,
            'approval_required' => $this->approvalRequired(),
            'change_request' => is_array($changeRequest) ? [
                'reason' => $changeRequest['reason'] ?? null,
                'requested_at' => $changeRequest['requested_at'] ?? null,
            ] : null,
            'resubmission_count' => (int) ($article->metadata['resubmission_count'] ?? 0),
        ];
    }

    public function transform(Article $article): array
    {
        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'status' => $article->status,
            'rejection_reason' => $article->rejection_reason,
            'published_at' => $article->published_at?->toIso8601String(),
            'created_at' => $article->created_at?->toIso8601String(),
            'total_reads' => (int) $article->total_reads,
            'total_unique_reads' => (int) $article->total_unique_reads,
            'total_reading_seconds' => (int) $article->total_reading_seconds,
            'total_points_generated' => (int) $article->total_points_generated,
            'read_time_minutes' => $article->read_time !== null ? (int) $article->read_time : null,
            'cover_image_url' => ArticleMedia::coverUrl($article),
            'images' => ArticleMedia::galleryPayload($article),
        ];
    }

    /** `publish` is the UI's word; `pending_review` / `published` are accepted for older clients. */
    protected function wantsPublish(?string $status): bool
    {
        return in_array($status, ['publish', 'published', 'pending_review'], true);
    }

    protected function publishTargetStatus(): string
    {
        return $this->approvalRequired() ? 'pending_review' : 'published';
    }
}
