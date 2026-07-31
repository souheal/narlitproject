<?php

namespace App\Services\Organization;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizationDashboardService
{
    public function dashboard(User $user, OrganizationProfile $organization): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $chartStart = $now->copy()->subDays(29)->startOfDay();

        $articleIds = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->pluck('id');

        $readsThisMonthQuery = ArticleRead::query()
            ->whereIn('article_id', $articleIds)
            ->where('created_at', '>=', $monthStart);

        $totalReadsThisMonth = (int) (clone $readsThisMonthQuery)->count();
        $uniqueReadersThisMonth = (int) (clone $readsThisMonthQuery)->distinct('user_id')->count('user_id');

        $totalReadsAllTime = (int) ArticleRead::query()
            ->whereIn('article_id', $articleIds)
            ->count();

        $earnedThisMonth = (float) ImpactTransaction::query()
            ->where('organization_profile_id', $organization->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('amount');

        $earnedAllTime = (float) ImpactTransaction::query()
            ->where('organization_profile_id', $organization->id)
            ->sum('amount');

        $pendingPayout = (float) DB::table('payout_items')
            ->where('organization_profile_id', $organization->id)
            ->whereIn('transfer_status', ['pending', 'failed'])
            ->sum('payout_amount');

        $totalArticles = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->count();

        $publishedArticles = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->where('status', 'published')
            ->count();

        $pendingArticles = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->where('status', 'pending_review')
            ->count();

        $recentArticles = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Article $article): array => [
                'public_id' => $article->public_id,
                'title' => $article->title,
                'status' => $article->status,
                'published_at' => $article->published_at?->toIso8601String(),
                'total_reads' => (int) $article->total_reads,
                'total_unique_reads' => (int) $article->total_unique_reads,
                'total_reading_seconds' => (int) $article->total_reading_seconds,
            ])
            ->all();

        $readsByDay = $this->readsByDaySeries($articleIds->all(), $chartStart, $now);
        $earningsByMonth = $this->earningsByMonthSeries($organization->id, $now, 6);
        $monthlyReads = $this->readsByMonthSeries($articleIds->all(), $now, 6);

        $currency = (string) config('services.stripe.currency', 'USD');
        $name = $organization->organization_name;
        $initials = $this->initialsFromName($name);
        $logoUrl = $organization->metadata['logo_url'] ?? null;

        $stats = [
            'total_articles' => (int) $totalArticles,
            'published_articles' => (int) $publishedArticles,
            'pending_articles' => (int) $pendingArticles,
            'total_reads_this_month' => $totalReadsThisMonth,
            'total_reads_all_time' => $totalReadsAllTime,
            'unique_readers_this_month' => $uniqueReadersThisMonth,
            'total_earned_this_month' => $this->money($earnedThisMonth),
            'total_earned_all_time' => $this->money($earnedAllTime),
            // Aliases used by the frontend markup
            'earned_this_month' => $this->money($earnedThisMonth),
            'earned_all_time' => $this->money($earnedAllTime),
            'pending_payout' => $this->money($pendingPayout),
        ];

        return [
            'organization' => [
                'public_id' => $organization->public_id,
                'name' => $name,
                'organization_name' => $name,
                'email' => $user->email,
                'verification_status' => $organization->verification_status,
                'irs_verified' => (bool) $organization->irs_verified,
                'website' => $organization->website,
                'logo_url' => $logoUrl,
                'initials' => $initials,
            ],
            'stats' => $stats,
            'charts' => [
                'reads_by_day' => $readsByDay,
                'earnings_by_month' => $earningsByMonth,
            ],
            'monthly_reads' => $monthlyReads,
            'recent_articles' => $recentArticles,
            'currency' => $currency,
        ];
    }

    /**
     * @param  array<int, int>  $articleIds
     * @return array<int, array<string, mixed>>
     */
    protected function readsByDaySeries(array $articleIds, Carbon $start, Carbon $end): array
    {
        $rows = collect();

        if ($articleIds !== []) {
            $rows = DB::table('article_reads')
                ->selectRaw('DATE(created_at) as day, COUNT(*) as value')
                ->whereIn('article_id', $articleIds)
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('day')
                ->pluck('value', 'day');
        }

        $series = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor <= $last) {
            $day = $cursor->toDateString();
            $series[] = [
                'date' => $day,
                'count' => (int) ($rows[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function earningsByMonthSeries(int $organizationProfileId, Carbon $end, int $months): array
    {
        $series = [];
        $cursor = $end->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $amount = (float) ImpactTransaction::query()
                ->where('organization_profile_id', $organizationProfileId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $series[] = [
                'month' => $cursor->format('Y-m'),
                'amount' => $this->money($amount),
            ];

            $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    /**
     * @param  array<int, int>  $articleIds
     * @return array<int, array<string, mixed>>
     */
    protected function readsByMonthSeries(array $articleIds, Carbon $end, int $months): array
    {
        $series = [];
        $cursor = $end->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $reads = 0;
            if ($articleIds !== []) {
                $reads = ArticleRead::query()
                    ->whereIn('article_id', $articleIds)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
            }

            $series[] = [
                'month' => $cursor->format('M Y'),
                'reads' => (int) $reads,
            ];

            $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    protected function initialsFromName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return 'OR';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'OR';
    }
}
