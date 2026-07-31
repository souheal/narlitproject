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
