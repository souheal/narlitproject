<?php

namespace App\Services\Member;

use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MemberImpactService
{
    public function show(User $user): array
    {
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);
        $currency = (string) config('services.stripe.currency', 'USD');

        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();

        $totalDonated = (float) ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        $monthlyDonated = (float) ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $nextMonthStart])
            ->sum('amount');

        $completedReads = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', $completedPercent)
            ->count();

        $uniqueArticles = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', $completedPercent)
            ->distinct('article_id')
            ->count('article_id');

        $organizationsSupported = ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->distinct('organization_profile_id')
            ->count('organization_profile_id');

        $readingSeconds = (int) ArticleRead::query()
            ->where('user_id', $user->id)
            ->sum('reading_seconds');

        $monthly = $this->monthlyBreakdown($user);
        $organizations = $this->organizationBreakdown($user);
        $recentTransactions = $this->recentTransactions($user);
        $recentReads = $this->recentReads($user);
        $currentStreak = $this->currentStreak($user);
        $longestStreak = $this->longestStreak($user);

        return [
            'impact' => [
                'currency' => $currency,
                'total_donated' => $this->money($totalDonated),
                'articles_read' => $completedReads,
                'nonprofits_supported' => $organizationsSupported,
                'day_streak' => $currentStreak,
                'totals' => [
                    'donated_all_time' => $this->money($totalDonated),
                    'donated_this_month' => $this->money($monthlyDonated),
                    'articles_read' => $completedReads,
                    'unique_articles' => $uniqueArticles,
                    'organizations_supported' => $organizationsSupported,
                    'reading_seconds' => $readingSeconds,
                    'day_streak' => $currentStreak,
                    'longest_streak' => $longestStreak,
                ],
                'monthly' => $monthly,
                'organizations' => $organizations,
                'by_organization' => $organizations,
                'recent_transactions' => $recentTransactions,
                'recent_reads' => $recentReads,
            ],
        ];
    }

    protected function monthlyBreakdown(User $user): array
    {
        $months = collect(range(0, 5))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset))
            ->reverse()
            ->values();

        $results = [];
        foreach ($months as $monthStart) {
            $nextMonth = $monthStart->copy()->addMonth();
            $donated = (float) ImpactTransaction::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $nextMonth])
                ->sum('amount');
            $reads = (int) ArticleRead::query()
                ->where('user_id', $user->id)
                ->where('read_percent', '>=', (int) config('services.impact.completed_read_percent', 80))
                ->whereBetween('created_at', [$monthStart, $nextMonth])
                ->count();
            $results[] = [
                'month' => $monthStart->format('Y-m'),
                'amount' => $this->money($donated),
                'donated' => round($donated, 2),
                'reads' => $reads,
            ];
        }

        return $results;
    }

    protected function organizationBreakdown(User $user): array
    {
        $reads = ArticleRead::query()
            ->with('article.organizationProfile')
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', (int) config('services.impact.completed_read_percent', 80))
            ->get();

        $transactionsByOrg = ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('organization_profile_id');

        $firstDates = ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->groupBy('organization_profile_id');

        return $reads
            ->groupBy(fn (ArticleRead $r): string => (string) $r->article?->organization_profile_id)
            ->map(function (Collection $orgReads) use ($transactionsByOrg, $firstDates) {
                $first = $orgReads->first();
                $profile = $first?->article?->organizationProfile;
                $orgId = (string) $first?->article?->organization_profile_id;
                $donated = (float) ($transactionsByOrg->get($orgId)?->sum('amount') ?? 0);
                $firstSupported = $firstDates->get($orgId)?->first()?->created_at?->toIso8601String();

                return [
                    'public_id' => $profile?->public_id,
                    'name' => $profile?->organization_name,
                    'organization_public_id' => $profile?->public_id,
                    'organization_name' => $profile?->organization_name,
                    'reads' => $orgReads->count(),
                    'amount' => $this->money($donated),
                    'amount_donated' => $this->money($donated),
                    'first_supported_at' => $firstSupported,
                ];
            })
            ->sortByDesc('reads')
            ->values()
            ->all();
    }

    protected function recentTransactions(User $user): array
    {
        return ImpactTransaction::query()
            ->with(['organizationProfile', 'article'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ImpactTransaction $tx): array => [
                'public_id' => $tx->public_id,
                'organization_name' => $tx->organizationProfile?->organization_name ?? 'Unknown',
                'article_title' => $tx->article?->title ?? 'Untitled',
                'amount' => $this->money((float) $tx->amount),
                'created_at' => $tx->created_at?->toIso8601String(),
            ])
            ->all();
    }

    protected function recentReads(User $user): array
    {
        $amountPerRead = (float) config('services.impact.amount_per_completed_read', 0.07);

        return ArticleRead::query()
            ->with(['article.organizationProfile'])
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', (int) config('services.impact.completed_read_percent', 80))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (ArticleRead $r) use ($amountPerRead): array {
                $article = $r->article;
                return [
                    'article' => $article ? [
                        'public_id' => $article->public_id,
                        'title' => $article->title,
                    ] : null,
                    'organization' => $article?->organizationProfile ? [
                        'public_id' => $article->organizationProfile->public_id,
                        'name' => $article->organizationProfile->organization_name,
                    ] : null,
                    'read_at' => $r->created_at?->toIso8601String(),
                    'amount' => $this->money($amountPerRead),
                ];
            })
            ->all();
    }

    protected function currentStreak(User $user): int
    {
        $dates = ArticleRead::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $streak = 0;
        $cursor = now()->toDateString();
        foreach ($dates as $date) {
            if ($date === $cursor) {
                $streak++;
                $cursor = Carbon::parse($cursor)->subDay()->toDateString();
            } else {
                break;
            }
        }

        return $streak;
    }

    protected function longestStreak(User $user): int
    {
        $dates = ArticleRead::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        $longest = 0;
        $current = 0;
        $prev = null;
        foreach ($dates as $date) {
            if ($prev !== null && Carbon::parse($prev)->addDay()->toDateString() === $date) {
                $current++;
            } else {
                $current = 1;
            }
            $longest = max($longest, $current);
            $prev = $date;
        }

        return $longest;
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
