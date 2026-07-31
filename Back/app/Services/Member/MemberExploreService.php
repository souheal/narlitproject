<?php

namespace App\Services\Member;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class MemberExploreService
{
    public function index(User $user): array
    {
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);

        $recentArticles = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $featured = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->whereNotNull('featured_at')
            ->orderByDesc('featured_at')
            ->limit(8)
            ->get();

        $trending = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->orderByDesc('total_reads')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $forYou = $this->forYou($user, $completedPercent);
        $newThisWeek = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $newOrganizations = OrganizationProfile::query()
            ->withCount(['articles as article_count' => function ($q): void {
                $q->where('status', 'published');
            }])
            ->where('verification_status', 'approved')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $categories = $recentArticles
            ->filter(fn (Article $a) => (string) $a->category !== '')
            ->groupBy(fn (Article $a): string => (string) $a->category)
            ->map(fn (Collection $group, string $category): array => [
                'name' => $category,
                'category' => $category,
                'article_count' => $group->count(),
                'cover' => optional($group->first()->organizationProfile)->organization_name,
            ])
            ->values()
            ->all();

        $byCategory = collect($categories)->map(function (array $item) use ($recentArticles, $user, $completedPercent): array {
            $items = $recentArticles
                ->filter(fn (Article $a): bool => (string) $a->category === (string) $item['name'])
                ->take(6)
                ->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))
                ->values()
                ->all();

            return [
                'category' => $item['name'],
                'icon' => null,
                'articles' => $items,
            ];
        })->values()->all();

        return [
            'explore' => [
                'featured' => $featured->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))->values()->all(),
                'trending' => $trending->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))->values()->all(),
                'for_you' => $forYou,
                'by_category' => $byCategory,
                'new_this_week' => $newThisWeek->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))->values()->all(),
            ],
            'categories' => $categories,
            'trending' => $trending->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))->values()->all(),
            'for_you' => $forYou,
            'new_organizations' => $newOrganizations->map(fn (OrganizationProfile $o): array => [
                'public_id' => $o->public_id,
                'name' => $o->organization_name,
                'article_count' => (int) ($o->article_count ?? 0),
                'mission' => $o->metadata['mission_statement'] ?? null,
            ])->values()->all(),
        ];
    }

    protected function forYou(User $user, int $completedPercent): array
    {
        $readIds = ArticleRead::query()
            ->where('user_id', $user->id)
            ->pluck('article_id')
            ->all();

        $articles = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->when(! empty($readIds), fn ($q) => $q->whereNotIn('id', $readIds))
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        return $articles->map(fn (Article $a): array => $this->article($a, $user, $completedPercent))->values()->all();
    }

    protected function article(Article $article, User $user, int $completedPercent): array
    {
        $isRead = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('article_id', $article->id)
            ->where('read_percent', '>=', $completedPercent)
            ->exists();

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'organization' => [
                'public_id' => $article->organizationProfile?->public_id,
                'name' => $article->organizationProfile?->organization_name,
            ],
            'read_time_minutes' => $article->read_time,
            'is_read' => $isRead,
            'cta_label' => $isRead ? 'Read again' : 'Read now',
            'published_at' => $article->published_at?->toIso8601String(),
        ];
    }
}
