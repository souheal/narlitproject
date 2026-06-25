<?php

namespace App\Services\Impact;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\ImpactWallet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserImpactDashboardService
{
    public function dashboard(User $user): array
    {
        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $previousMonthStart = $monthStart->copy()->subMonth();
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);

        $subscription = $this->activeSubscription($user);
        $monthlyDonation = (float) ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $nextMonthStart])
            ->sum('amount');

        $previousMonthlyDonation = (float) ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$previousMonthStart, $monthStart])
            ->sum('amount');

        $articlesRead = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('read_percent', '>=', $completedPercent)
            ->count();

        $nonprofitsFunded = ImpactTransaction::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $nextMonthStart])
            ->distinct('organization_profile_id')
            ->count('organization_profile_id');

        return [
            'member' => [
                'name' => $user->full_name,
                'username' => $user->username,
                'initials' => $this->initials($user->full_name ?: $user->username ?: $user->email),
                'subscription_status' => $subscription?->status ?? 'inactive',
                'subscription_plan' => $subscription?->plan,
                'subscription_active' => $subscription !== null,
            ],
            'impact' => [
                'donated_this_month' => $this->money($monthlyDonation),
                'donated_delta_from_last_month' => $this->money($monthlyDonation - $previousMonthlyDonation),
                'articles_read' => $articlesRead,
                'articles_read_label' => 'All time',
                'nonprofits_funded_this_month' => $nonprofitsFunded,
                'day_streak' => $this->readingStreak($user),
            ],
            'stories' => $this->stories($user, 3),
            'subscription_breakdown' => $this->subscriptionBreakdown($subscription, $monthlyDonation),
            'funding_breakdown' => $this->fundingBreakdown($user, $monthStart, $nextMonthStart),
        ];
    }

    public function articles(User $user, int $perPage = 10): array
    {
        $articles = Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return [
            'articles' => $articles->through(fn (Article $article): array => $this->articlePayload($article, $user)),
        ];
    }

    public function recordRead(User $user, Article $article, array $data): array
    {
        $readPercent = min(100, max(0, (int) ($data['read_percent'] ?? 100)));
        $readingSeconds = max(0, (int) ($data['reading_seconds'] ?? 0));
        $completedPercent = (int) config('services.impact.completed_read_percent', 80);
        $completed = $readPercent >= $completedPercent;
        $points = $completed ? max(1, (int) ceil(max($readPercent, $readingSeconds / 6) / 10)) : 0;
        $alreadyRead = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('article_id', $article->id)
            ->where('read_percent', '>=', $completedPercent)
            ->exists();

        return DB::transaction(function () use ($user, $article, $data, $readPercent, $readingSeconds, $completed, $points, $alreadyRead): array {
            $read = ArticleRead::query()->create([
                'article_id' => $article->id,
                'user_id' => $user->id,
                'read_percent' => $readPercent,
                'reading_seconds' => $readingSeconds,
                'points_earned' => $points,
                'counted_for_payout' => $completed,
                'session_id' => $data['session_id'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'device_type' => $data['device_type'] ?? null,
                'country' => $data['country'] ?? null,
            ]);

            $article->increment('total_reads');
            $article->increment('total_reading_seconds', $readingSeconds);
            $article->increment('total_points_generated', $points);

            if ($completed && ! $alreadyRead) {
                $article->increment('total_unique_reads');
            }

            $transaction = null;

            if ($completed) {
                $transaction = ImpactTransaction::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'organization_profile_id' => $article->organization_profile_id,
                    'article_id' => $article->id,
                    'amount' => $this->money((float) config('services.impact.amount_per_completed_read', 0.07)),
                    'points_generated' => $points,
                    'transaction_month' => now()->startOfMonth()->toDateString(),
                    'metadata' => [
                        'source' => 'article_read',
                        'article_read_id' => $read->id,
                    ],
                ]);

                $this->refreshWallet($user);
            }

            return [
                'article' => $this->articlePayload($article->refresh(), $user),
                'read' => [
                    'id' => $read->id,
                    'read_percent' => $read->read_percent,
                    'reading_seconds' => $read->reading_seconds,
                    'points_earned' => $read->points_earned,
                    'counted_for_payout' => $read->counted_for_payout,
                ],
                'impact_transaction' => $transaction ? [
                    'public_id' => $transaction->public_id,
                    'amount' => $transaction->amount,
                    'points_generated' => $transaction->points_generated,
                    'transaction_month' => $transaction->transaction_month->toDateString(),
                ] : null,
            ];
        });
    }

    public function articlePayload(Article $article, User $user): array
    {
        $article->loadMissing('organizationProfile');
        $readPercent = (int) config('services.impact.completed_read_percent', 80);
        $isRead = ArticleRead::query()
            ->where('user_id', $user->id)
            ->where('article_id', $article->id)
            ->where('read_percent', '>=', $readPercent)
            ->exists();

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'organization' => [
                'public_id' => $article->organizationProfile?->public_id,
                'name' => $article->organizationProfile?->organization_name,
            ],
            'read_time_minutes' => $article->read_time,
            'published_at' => $article->published_at?->toIso8601String(),
            'is_read' => $isRead,
            'cta_label' => $isRead ? 'Read again' : 'Read now',
        ];
    }

    protected function stories(User $user, int $limit): array
    {
        return Article::query()
            ->with('organizationProfile')
            ->where('status', 'published')
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Article $article): array => $this->articlePayload($article, $user))
            ->all();
    }

    protected function fundingBreakdown(User $user, Carbon $monthStart, Carbon $nextMonthStart): array
    {
        $reads = ArticleRead::query()
            ->with('article.organizationProfile')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$monthStart, $nextMonthStart])
            ->where('read_percent', '>=', (int) config('services.impact.completed_read_percent', 80))
            ->get();

        $totalReads = max(1, $reads->count());

        return $reads
            ->groupBy(fn (ArticleRead $read): string => (string) $read->article?->organization_profile_id)
            ->map(function (Collection $organizationReads) use ($totalReads): array {
                $profile = $organizationReads->first()?->article?->organizationProfile;
                $count = $organizationReads->count();

                return [
                    'organization_public_id' => $profile?->public_id,
                    'organization_name' => $profile?->organization_name,
                    'reads' => $count,
                    'percent' => (int) round(($count / $totalReads) * 100),
                ];
            })
            ->sortByDesc('reads')
            ->values()
            ->all();
    }

    protected function subscriptionBreakdown(?Subscription $subscription, float $monthlyDonation): array
    {
        $amount = $subscription ? (float) $subscription->amount : 0.0;
        $nonprofitShare = (int) config('services.impact.nonprofit_share_percent', 33);
        $operationsShare = (int) config('services.impact.operations_share_percent', 33);
        $growthShare = (int) config('services.impact.growth_share_percent', 34);

        return [
            'subscription_amount' => $this->money($amount),
            'currency' => $subscription?->currency ?? config('services.stripe.currency', 'USD'),
            'nonprofits' => [
                'percent' => $nonprofitShare,
                'amount' => $this->money($monthlyDonation),
            ],
            'operations' => [
                'percent' => $operationsShare,
                'amount' => $this->money($amount * ($operationsShare / 100)),
            ],
            'growth' => [
                'percent' => $growthShare,
                'amount' => $this->money($amount * ($growthShare / 100)),
            ],
        ];
    }

    protected function activeSubscription(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->first();
    }

    protected function readingStreak(User $user): int
    {
        $dates = ArticleRead::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $streak = 0;
        $cursor = now()->toDateString();

        foreach ($dates as $date) {
            if ($date === $cursor) {
                $streak++;
                $cursor = Carbon::parse($cursor)->subDay()->toDateString();
            } elseif ($streak === 0 && $date > $cursor) {
                continue;
            } else {
                break;
            }
        }

        return $streak;
    }

    protected function refreshWallet(User $user): void
    {
        ImpactWallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'total_impact_amount' => $this->money((float) ImpactTransaction::query()->where('user_id', $user->id)->sum('amount')),
                'total_articles_read' => ArticleRead::query()
                    ->where('user_id', $user->id)
                    ->where('read_percent', '>=', (int) config('services.impact.completed_read_percent', 80))
                    ->count(),
                'total_points' => ArticleRead::query()->where('user_id', $user->id)->sum('points_earned'),
                'total_organizations_supported' => ImpactTransaction::query()
                    ->where('user_id', $user->id)
                    ->distinct('organization_profile_id')
                    ->count('organization_profile_id'),
            ],
        );
    }

    protected function initials(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
