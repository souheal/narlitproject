<?php

namespace App\Services\PublicApi;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PublicArticleService
{
    public function show(string $publicId, ?User $user = null): array
    {
        $article = Article::query()
            ->with('organizationProfile')
            ->where('public_id', $publicId)
            ->first();

        if ($article === null) {
            throw new ApiException('The requested article was not found.', 404);
        }

        $ownerUserId = $article->organizationProfile?->user_id;
        $isOwner = $user !== null
            && $ownerUserId !== null
            && (int) $user->id === (int) $ownerUserId;

        if ($article->status !== 'published' && ! $isOwner) {
            throw new ApiException('The requested article was not found.', 404);
        }

        $data = $this->transform($article);

        if ($user !== null) {
            $data['is_read'] = ArticleRead::query()
                ->where('user_id', $user->id)
                ->where('article_id', $article->id)
                ->exists();
        }

        return ['article' => $data];
    }

    /**
     * Published articles for the landing page, featured ones first.
     * Articles an admin has featured sort above the rest; within each band the
     * most recently published wins.
     */
    public function featured(int $limit = 3): array
    {
        $limit = max(1, min($limit, 12));

        $articles = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->orderByRaw('CASE WHEN featured_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Article $article): array => $this->transform($article))
            ->all();

        return ['articles' => $articles];
    }

    protected function transform(Article $article): array
    {
        $organization = $article->organizationProfile;
        $metadata = is_array($article->metadata) ? $article->metadata : [];
        $coverPath = $metadata['cover_image_path'] ?? null;

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'category' => $article->category,
            'organization' => [
                'public_id' => $organization?->public_id,
                'name' => $organization?->organization_name,
                'website' => $organization?->website,
                'verification_status' => $organization?->verification_status,
            ],
            'read_time_minutes' => $article->read_time,
            'published_at' => $article->published_at?->toIso8601String(),
            'total_reads' => (int) $article->total_reads,
            'total_unique_reads' => (int) $article->total_unique_reads,
            'cover_image_url' => $coverPath ? Storage::url($coverPath) : null,
        ];
    }
}
