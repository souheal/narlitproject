<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\ArticleRead;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function dashboard(string $period): array
    {
        $range = $this->rangeForPeriod($period);
        $previousRange = $this->previousRange($range['start'], $range['end']);

        $metrics = [
            'monthly_recurring_revenue' => $this->metric(
                $this->mrrAt($range['end']),
                $this->mrrAt($previousRange['end']),
                $this->subscriptionAmountSparkline($range['start'], $range['end']),
                'currency'
            ),
            'active_subscribers' => $this->metric(
                $this->activeSubscribersAt($range['end']),
                $this->activeSubscribersAt($previousRange['end']),
                $this->subscriptionCountSparkline($range['start'], $range['end']),
                'count'
            ),
            'total_article_reads' => $this->metric(
                $this->articleReadsBetween($range['start'], $range['end']),
                $this->articleReadsBetween($previousRange['start'], $previousRange['end']),
                $this->countSparkline('article_reads', $range['start'], $range['end']),
                'count'
            ),
            'total_impact_amount' => $this->metric(
                $this->impactAmountBetween($range['start'], $range['end']),
                $this->impactAmountBetween($previousRange['start'], $previousRange['end']),
                $this->amountSparkline('impact_transactions', 'amount', $range['start'], $range['end']),
                'currency'
            ),
            'active_organizations' => $this->metric(
                $this->approvedOrganizationsAt($range['end']),
                $this->approvedOrganizationsAt($previousRange['end']),
                $this->organizationSparkline($range['start'], $range['end']),
                'count'
            ),
            'pending_organization_reviews' => $this->metric(
                $this->pendingOrganizations(),
                $this->pendingOrganizationsCreatedBefore($range['start']),
                $this->pendingOrganizationSparkline($range['start'], $range['end']),
                'count'
            ),
            'pending_article_reviews' => $this->metric(
                $this->pendingArticles(),
                $this->pendingArticlesCreatedBefore($range['start']),
                $this->pendingArticleSparkline($range['start'], $range['end']),
                'count'
            ),
            'failed_or_pending_payouts' => $this->metric(
                $this->failedOrPendingPayouts(),
                $this->failedOrPendingPayoutsCreatedBefore($range['start']),
                $this->payoutSparkline($range['start'], $range['end']),
                'count'
            ),
        ];

        return [
            'period' => [
                'key' => $period,
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'previous_start' => $previousRange['start']->toDateString(),
                'previous_end' => $previousRange['end']->toDateString(),
            ],
            'metrics' => $metrics,
            'previous_period_comparison' => collect($metrics)
                ->map(fn (array $metric): array => [
                    'current_value' => $metric['value'],
                    'previous_value' => $metric['previous_value'],
                    'change_percent' => $metric['change_percent'],
                    'trend' => $metric['trend'],
                ])
                ->all(),
            'review_queues' => $this->reviewQueues(),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    protected function rangeForPeriod(string $period): array
    {
        $end = now();

        $start = match ($period) {
            'last_7_days' => $end->copy()->subDays(6)->startOfDay(),
            'last_30_days' => $end->copy()->subDays(29)->startOfDay(),
            'last_90_days' => $end->copy()->subDays(89)->startOfDay(),
            'current_year' => $end->copy()->startOfYear(),
        };

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function previousRange(Carbon $start, Carbon $end): array
    {
        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subSecond();

        return [
            'start' => $start->copy()->subDays($days),
            'end' => $previousEnd,
        ];
    }

    protected function metric(float|int $value, float|int $previousValue, array $sparkline, string $format): array
    {
        $change = $this->changePercent((float) $value, (float) $previousValue);

        return [
            'value' => $format === 'currency' ? $this->money((float) $value) : (int) $value,
            'previous_value' => $format === 'currency' ? $this->money((float) $previousValue) : (int) $previousValue,
            'change_percent' => $change,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'sparkline' => $sparkline,
            'format' => $format,
        ];
    }

    protected function changePercent(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    protected function mrrAt(Carbon $date): float
    {
        return (float) Subscription::query()
            ->where('status', 'active')
            ->where('started_at', '<=', $date)
            ->where('expires_at', '>', $date)
            ->get(['plan', 'amount'])
            ->sum(fn (Subscription $subscription): float => $subscription->plan === 'yearly'
                ? ((float) $subscription->amount) / 12
                : (float) $subscription->amount);
    }

    protected function activeSubscribersAt(Carbon $date): int
    {
        return Subscription::query()
            ->where('status', 'active')
            ->where('started_at', '<=', $date)
            ->where('expires_at', '>', $date)
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function articleReadsBetween(Carbon $start, Carbon $end): int
    {
        return ArticleRead::query()->whereBetween('created_at', [$start, $end])->count();
    }

    protected function impactAmountBetween(Carbon $start, Carbon $end): float
    {
        return (float) ImpactTransaction::query()->whereBetween('created_at', [$start, $end])->sum('amount');
    }

    protected function approvedOrganizationsAt(Carbon $date): int
    {
        return OrganizationProfile::query()
            ->where('verification_status', 'approved')
            ->where('created_at', '<=', $date)
            ->count();
    }

    protected function pendingOrganizations(): int
    {
        return OrganizationProfile::query()->where('verification_status', 'pending')->count();
    }

    protected function pendingOrganizationsCreatedBefore(Carbon $date): int
    {
        return OrganizationProfile::query()
            ->where('verification_status', 'pending')
            ->where('created_at', '<', $date)
            ->count();
    }

    protected function pendingArticles(): int
    {
        return Article::query()->where('status', 'pending_review')->count();
    }

    protected function pendingArticlesCreatedBefore(Carbon $date): int
    {
        return Article::query()
            ->where('status', 'pending_review')
            ->where('created_at', '<', $date)
            ->count();
    }

    protected function failedOrPendingPayouts(): int
    {
        return DB::table('payout_batches')->whereIn('status', ['failed', 'pending'])->count();
    }

    protected function failedOrPendingPayoutsCreatedBefore(Carbon $date): int
    {
        return DB::table('payout_batches')
            ->whereIn('status', ['failed', 'pending'])
            ->where('created_at', '<', $date)
            ->count();
    }

    protected function reviewQueues(): array
    {
        return [
            'pending_organizations' => [
                'count' => $this->pendingOrganizations(),
                'href' => '/admin/organizations?status=pending',
                'items' => OrganizationProfile::query()
                    ->where('verification_status', 'pending')
                    ->latest()
                    ->limit(5)
                    ->get(['public_id', 'organization_name', 'created_at'])
                    ->map(fn (OrganizationProfile $profile): array => [
                        'public_id' => $profile->public_id,
                        'title' => $profile->organization_name,
                        'created_at' => $profile->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'pending_articles' => [
                'count' => $this->pendingArticles(),
                'href' => '/admin/articles?status=pending_review',
                'items' => Article::query()
                    ->with('organizationProfile:id,organization_name')
                    ->where('status', 'pending_review')
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'public_id', 'organization_profile_id', 'title', 'created_at'])
                    ->map(fn (Article $article): array => [
                        'public_id' => $article->public_id,
                        'title' => $article->title,
                        'organization_name' => $article->organizationProfile?->organization_name,
                        'created_at' => $article->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'failed_payouts' => [
                'count' => DB::table('payout_batches')->where('status', 'failed')->count(),
                'href' => '/admin/payouts?status=failed',
                'items' => DB::table('payout_batches')
                    ->where('status', 'failed')
                    ->latest()
                    ->limit(5)
                    ->get(['public_id', 'batch_month', 'total_pool', 'total_distributed', 'created_at'])
                    ->map(fn (object $payout): array => [
                        'public_id' => $payout->public_id,
                        'title' => (string) $payout->batch_month,
                        'total_pool' => $this->money((float) $payout->total_pool),
                        'total_distributed' => $this->money((float) $payout->total_distributed),
                        'created_at' => Carbon::parse($payout->created_at)->toIso8601String(),
                    ])
                    ->all(),
            ],
        ];
    }

    protected function recentActivity(): array
    {
        return [
            'admin_actions' => DB::table('admin_logs')
                ->join('users', 'users.id', '=', 'admin_logs.admin_id')
                ->latest('admin_logs.created_at')
                ->limit(5)
                ->get(['admin_logs.action', 'admin_logs.entity_type', 'admin_logs.entity_id', 'admin_logs.created_at', 'users.full_name as admin_name'])
                ->map(fn (object $log): array => [
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'admin_name' => $log->admin_name,
                    'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
                ])
                ->all(),
            'subscriptions' => Subscription::query()
                ->with('user:id,full_name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'public_id', 'user_id', 'plan', 'amount', 'currency', 'status', 'created_at'])
                ->map(fn (Subscription $subscription): array => [
                    'public_id' => $subscription->public_id,
                    'subscriber_name' => $subscription->user?->full_name,
                    'subscriber_email' => $subscription->user?->email,
                    'plan' => $subscription->plan,
                    'amount' => $this->money((float) $subscription->amount),
                    'currency' => $subscription->currency,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at?->toIso8601String(),
                ])
                ->all(),
            'payments' => Payment::query()
                ->with('user:id,full_name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'public_id', 'user_id', 'amount', 'currency', 'status', 'paid_at', 'created_at'])
                ->map(fn (Payment $payment): array => [
                    'public_id' => $payment->public_id,
                    'payer_name' => $payment->user?->full_name,
                    'payer_email' => $payment->user?->email,
                    'amount' => $this->money((float) $payment->amount),
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                ])
                ->all(),
            'organization_approvals' => OrganizationProfile::query()
                ->where('verification_status', 'approved')
                ->latest('reviewed_at')
                ->limit(5)
                ->get(['public_id', 'organization_name', 'reviewed_at'])
                ->map(fn (OrganizationProfile $profile): array => [
                    'public_id' => $profile->public_id,
                    'organization_name' => $profile->organization_name,
                    'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
                ])
                ->all(),
            'published_articles' => Article::query()
                ->with('organizationProfile:id,organization_name')
                ->where('status', 'published')
                ->latest('published_at')
                ->limit(5)
                ->get(['id', 'public_id', 'organization_profile_id', 'title', 'published_at'])
                ->map(fn (Article $article): array => [
                    'public_id' => $article->public_id,
                    'title' => $article->title,
                    'organization_name' => $article->organizationProfile?->organization_name,
                    'published_at' => $article->published_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    protected function countSparkline(string $table, Carbon $start, Carbon $end): array
    {
        $rows = DB::table($table)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as value')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->pluck('value', 'day');

        return $this->dailySeries($start, $end, fn (string $day): int => (int) ($rows[$day] ?? 0));
    }

    protected function amountSparkline(string $table, string $column, Carbon $start, Carbon $end): array
    {
        $rows = DB::table($table)
            ->selectRaw("DATE(created_at) as day, SUM({$column}) as value")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->pluck('value', 'day');

        return $this->dailySeries($start, $end, fn (string $day): string => $this->money((float) ($rows[$day] ?? 0)));
    }

    protected function subscriptionAmountSparkline(Carbon $start, Carbon $end): array
    {
        return $this->dailySeries($start, $end, fn (string $day): string => $this->money($this->mrrAt(Carbon::parse($day)->endOfDay())));
    }

    protected function subscriptionCountSparkline(Carbon $start, Carbon $end): array
    {
        return $this->dailySeries($start, $end, fn (string $day): int => $this->activeSubscribersAt(Carbon::parse($day)->endOfDay()));
    }

    protected function organizationSparkline(Carbon $start, Carbon $end): array
    {
        return $this->dailySeries($start, $end, fn (string $day): int => $this->approvedOrganizationsAt(Carbon::parse($day)->endOfDay()));
    }

    protected function pendingOrganizationSparkline(Carbon $start, Carbon $end): array
    {
        $rows = OrganizationProfile::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as value')
            ->where('verification_status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->pluck('value', 'day');

        return $this->dailySeries($start, $end, fn (string $day): int => (int) ($rows[$day] ?? 0));
    }

    protected function pendingArticleSparkline(Carbon $start, Carbon $end): array
    {
        $rows = Article::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as value')
            ->where('status', 'pending_review')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->pluck('value', 'day');

        return $this->dailySeries($start, $end, fn (string $day): int => (int) ($rows[$day] ?? 0));
    }

    protected function payoutSparkline(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('payout_batches')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as value')
            ->whereIn('status', ['failed', 'pending'])
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->pluck('value', 'day');

        return $this->dailySeries($start, $end, fn (string $day): int => (int) ($rows[$day] ?? 0));
    }

    protected function dailySeries(Carbon $start, Carbon $end, callable $valueForDay): array
    {
        $series = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor <= $last) {
            $day = $cursor->toDateString();
            $series[] = [
                'date' => $day,
                'value' => $valueForDay($day),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
