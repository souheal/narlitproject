<?php

namespace App\Services\Member;

use App\Models\ArticleRead;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class MemberHistoryService
{
    public function index(User $user, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(50, $perPage));

        /** @var LengthAwarePaginator $paginator */
        $paginator = ArticleRead::query()
            ->with(['article.organizationProfile'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn (ArticleRead $r): array => $this->present($r));

        return [
            'history' => $paginator,
            'reads' => $paginator,
            'by_day' => $this->byDay($user),
            'category_breakdown' => $this->categoryBreakdown($user),
        ];
    }

    protected function present(ArticleRead $r): array
    {
        $article = $r->article;
        $completed = (int) $r->read_percent >= (int) config('services.impact.completed_read_percent', 80);

        return [
            'id' => $r->id,
            'public_id' => (string) $r->id,
            'read_percent' => (int) $r->read_percent,
            'reading_seconds' => (int) $r->reading_seconds,
            'points_earned' => (int) $r->points_earned,
            'counted_for_payout' => (bool) $r->counted_for_payout,
            'completed' => $completed,
            'read_at' => $r->created_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'article' => $article ? [
                'public_id' => $article->public_id,
                'title' => $article->title,
                'category' => $article->category,
                'organization' => [
                    'public_id' => $article->organizationProfile?->public_id,
                    'name' => $article->organizationProfile?->organization_name,
                ],
            ] : null,
        ];
    }

    protected function byDay(User $user): array
    {
        $since = now()->subDays(29)->startOfDay();

        $rows = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'reading_seconds']);

        $buckets = [];
        for ($i = 0; $i < 30; $i++) {
            $day = now()->subDays(29 - $i)->toDateString();
            $buckets[$day] = ['date' => $day, 'count' => 0, 'minutes' => 0];
        }

        foreach ($rows as $r) {
            $day = Carbon::parse($r->created_at)->toDateString();
            if (! isset($buckets[$day])) {
                continue;
            }
            $buckets[$day]['count']++;
            $buckets[$day]['minutes'] += (int) round(((int) $r->reading_seconds) / 60);
        }

        return array_values($buckets);
    }

    protected function categoryBreakdown(User $user): array
    {
        return ArticleRead::query()
            ->with('article')
            ->where('user_id', $user->id)
            ->get()
            ->groupBy(fn (ArticleRead $r): string => $r->article?->category ?? 'Uncategorized')
            ->map(fn ($group, $category): array => [
                'category' => (string) $category,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
