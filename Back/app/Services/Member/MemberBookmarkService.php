<?php

namespace App\Services\Member;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\Bookmark;
use App\Models\User;

class MemberBookmarkService
{
    public function list(User $user): array
    {
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);

        $bookmarks = Bookmark::query()
            ->with(['article.organizationProfile'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $items = $bookmarks->map(function (Bookmark $b) use ($user, $completedPercent): array {
            return $this->present($b, $user, $completedPercent);
        })->values()->all();

        return ['bookmarks' => $items];
    }

    public function create(User $user, string $articlePublicId): array
    {
        $article = Article::query()
            ->with('organizationProfile')
            ->where('public_id', $articlePublicId)
            ->first();

        if ($article === null) {
            throw new ApiException('Article not found.', 404);
        }

        $bookmark = Bookmark::query()->firstOrCreate([
            'user_id' => $user->id,
            'article_id' => $article->id,
        ]);
        $bookmark->refresh();
        $bookmark->setRelation('article', $article);

        $completedPercent = (int) config('services.impact.completed_read_percent', 80);

        return ['bookmark' => $this->present($bookmark, $user, $completedPercent)];
    }

    public function delete(User $user, string $articlePublicId): array
    {
        $article = Article::query()->where('public_id', $articlePublicId)->first();

        if ($article === null) {
            throw new ApiException('Article not found.', 404);
        }

        $deleted = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('article_id', $article->id)
            ->delete();

        return ['deleted' => $deleted > 0];
    }

    protected function present(Bookmark $b, User $user, int $completedPercent): array
    {
        $article = $b->article;
        $isRead = false;
        if ($article !== null) {
            $isRead = ArticleRead::query()
                ->where('user_id', $user->id)
                ->where('article_id', $article->id)
                ->where('read_percent', '>=', $completedPercent)
                ->exists();
        }

        return [
            'id' => $b->id,
            'public_id' => (string) $b->id,
            'article' => $article ? [
                'public_id' => $article->public_id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'category' => $article->category,
                'organization' => [
                    'public_id' => $article->organizationProfile?->public_id,
                    'name' => $article->organizationProfile?->organization_name,
                ],
                'published_at' => $article->published_at?->toIso8601String(),
                'read_time_minutes' => $article->read_time,
                'is_read' => $isRead,
            ] : null,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
