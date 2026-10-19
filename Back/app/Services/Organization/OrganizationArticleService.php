<?php

namespace App\Services\Organization;

use App\Models\Article;
use App\Models\OrganizationProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationArticleService
{
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

    public function findForOrganization(OrganizationProfile $organization, string $publicId): ?Article
    {
        return Article::query()
            ->where('organization_profile_id', $organization->id)
            ->where('public_id', $publicId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Article $article, array $data, ?UploadedFile $coverImage = null): Article
    {
        $payload = [];

        foreach (['title', 'excerpt', 'category'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $content = $data['content'] ?? $data['body'] ?? null;
        if (is_string($content) && trim($content) !== '') {
            $payload['content'] = $content;
        }

        if (array_key_exists('read_time', $data) && $data['read_time'] !== null) {
            $payload['read_time'] = (int) $data['read_time'];
        } elseif (array_key_exists('read_time_minutes', $data) && $data['read_time_minutes'] !== null) {
            $payload['read_time'] = (int) $data['read_time_minutes'];
        }

        $requestedStatus = $data['status'] ?? null;
        if (in_array($requestedStatus, ['draft', 'pending_review'], true)) {
            // Published or rejected articles that the org re-saves go back for re-review.
            $payload['status'] = $requestedStatus;
        }

        $metadata = is_array($article->metadata) ? $article->metadata : [];

        if ($coverImage instanceof UploadedFile) {
            $existing = $metadata['cover_image_path'] ?? null;
            if (is_string($existing) && $existing !== '') {
                try {
                    Storage::disk('public')->delete($existing);
                } catch (\Throwable) {
                    // Ignore failures to delete; the new upload still wins.
                }
            }
            $metadata['cover_image_path'] = $coverImage->store('articles/covers', 'public');
            $payload['metadata'] = $metadata;
        } elseif (array_key_exists('remove_cover_image', $data) && $data['remove_cover_image']) {
            $existing = $metadata['cover_image_path'] ?? null;
            if (is_string($existing) && $existing !== '') {
                try {
                    Storage::disk('public')->delete($existing);
                } catch (\Throwable) {
                    // Ignore.
                }
            }
            unset($metadata['cover_image_path']);
            $payload['metadata'] = $metadata !== [] ? $metadata : null;
        }

        $article->fill($payload)->save();

        return $article->fresh();
    }

    public function delete(Article $article): void
    {
        $metadata = is_array($article->metadata) ? $article->metadata : [];
        $coverPath = $metadata['cover_image_path'] ?? null;
        if (is_string($coverPath) && $coverPath !== '') {
            try {
                Storage::disk('public')->delete($coverPath);
            } catch (\Throwable) {
                // Ignore.
            }
        }

        $article->delete();
    }

    public function detail(Article $article): array
    {
        $data = $this->transform($article);
        $data['content'] = $article->content;
        $data['rejection_reason'] = $article->rejection_reason;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(OrganizationProfile $organization, array $data, ?UploadedFile $coverImage = null): Article
    {
        $title = (string) $data['title'];
        $requestedStatus = $data['status'] ?? null;

        $status = $organization->verification_status === 'approved'
            ? 'pending_review'
            : 'draft';

        if ($requestedStatus === 'draft') {
            $status = 'draft';
        }

        $metadata = [];

        if ($coverImage instanceof UploadedFile) {
            $path = $coverImage->store('articles/covers', 'public');
            $metadata['cover_image_path'] = $path;
        }

        $article = Article::create([
            'public_id' => (string) Str::uuid(),
            'organization_profile_id' => $organization->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.substr((string) Str::uuid(), 0, 8),
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'status' => $status,
            'read_time' => $data['read_time'] ?? null,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);

        return $article->fresh();
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
            'cover_image_url' => $this->coverImageUrl($article),
        ];
    }

    protected function coverImageUrl(Article $article): ?string
    {
        $path = $article->metadata['cover_image_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
