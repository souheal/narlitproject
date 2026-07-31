<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function combined(Request $request): array
    {
        [$start, $end] = $this->rangeFromShorthand($request);
        $days = (int) $start->diffInDays($end) + 1;
        $isMonthly = $days > 90;
        $interval = $isMonthly ? 'month' : 'day';

        $signups = DB::table('users')->whereBetween('created_at', [$start, $end])->count();
        $activated = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('email_verified_at')
            ->count();
        $paying = DB::table('subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->whereBetween('users.created_at', [$start, $end])
            ->where('subscriptions.status', 'active')
            ->distinct('users.id')
            ->count('users.id');

        $buckets = $this->buckets($start, $end, $interval);
        $signupRows = $this->countRows('users', 'created_at', $start, $end, $interval);
        $activationRows = DB::table('users')
            ->selectRaw($this->periodExpression('email_verified_at', $interval).' as bucket, COUNT(*) as value')
            ->whereBetween('email_verified_at', [$start, $end])
            ->groupBy('bucket')
            ->pluck('value', 'bucket');
        $readRows = $this->countRows('article_reads', 'created_at', $start, $end, $interval);
        $revenueRows = DB::table('payments')
            ->selectRaw($this->periodExpression('paid_at', $interval).' as bucket, SUM(amount) as value')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy('bucket')
            ->pluck('value', 'bucket');

        $signupSeries = $this->dateSeries($buckets, $signupRows);
        $activationSeries = $this->dateSeries($buckets, $activationRows);
        $readSeries = $this->dateSeries($buckets, $readRows);
        $revenueSeries = $this->dateAmountSeries($buckets, $revenueRows);

        $topOrgsRaw = DB::table('organization_profiles')
            ->leftJoin('articles', 'articles.organization_profile_id', '=', 'organization_profiles.id')
            ->leftJoin('article_reads', function ($join) use ($start, $end): void {
                $join->on('article_reads.article_id', '=', 'articles.id')
                    ->whereBetween('article_reads.created_at', [$start, $end]);
            })
            ->leftJoin('impact_transactions', function ($join) use ($start, $end): void {
                $join->on('impact_transactions.organization_profile_id', '=', 'organization_profiles.id')
                    ->whereBetween('impact_transactions.created_at', [$start, $end]);
            })
            ->selectRaw('organization_profiles.public_id, organization_profiles.organization_name')
            ->selectRaw('COUNT(DISTINCT article_reads.id) as reads')
            ->selectRaw('COALESCE(SUM(DISTINCT impact_transactions.amount), 0) as impact_amount')
            ->groupBy('organization_profiles.id', 'organization_profiles.public_id', 'organization_profiles.organization_name')
            ->orderByDesc('reads')
            ->limit(10)
            ->get();

        $topArticlesRaw = DB::table('articles')
            ->leftJoin('organization_profiles', 'organization_profiles.id', '=', 'articles.organization_profile_id')
            ->leftJoin('article_reads', function ($join) use ($start, $end): void {
                $join->on('article_reads.article_id', '=', 'articles.id')
                    ->whereBetween('article_reads.created_at', [$start, $end]);
            })
            ->selectRaw('articles.public_id, articles.title, organization_profiles.organization_name as organization')
            ->selectRaw('COUNT(DISTINCT article_reads.id) as reads')
            ->groupBy('articles.id', 'articles.public_id', 'articles.title', 'organization_profiles.organization_name')
            ->orderByDesc('reads')
            ->limit(10)
            ->get();

        $categoryReads = DB::table('articles')
            ->join('article_reads', 'article_reads.article_id', '=', 'articles.id')
            ->whereBetween('article_reads.created_at', [$start, $end])
            ->groupBy('articles.category')
            ->selectRaw("COALESCE(articles.category, 'Uncategorized') as category, COUNT(article_reads.id) as reads")
            ->get();
        $categoryTotal = max(1, (int) $categoryReads->sum('reads'));
        $categories = $categoryReads->map(fn (object $row): array => [
            'category' => (string) $row->category,
            'reads' => (int) $row->reads,
            'percent' => (int) round(((int) $row->reads / $categoryTotal) * 100),
        ])->values()->all();

        $funnelSteps = [
            'Sign up' => DB::table('users')->whereBetween('created_at', [$start, $end])->count(),
            'Verified email' => DB::table('users')->whereBetween('created_at', [$start, $end])->whereNotNull('email_verified_at')->count(),
            'Subscribed' => DB::table('subscriptions')
                ->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->whereBetween('users.created_at', [$start, $end])
                ->distinct('users.id')
                ->count('users.id'),
            'First read' => DB::table('article_reads')
                ->join('users', 'users.id', '=', 'article_reads.user_id')
                ->whereBetween('users.created_at', [$start, $end])
                ->distinct('article_reads.user_id')
                ->count('article_reads.user_id'),
        ];
        $funnelBase = max(1, (int) $funnelSteps['Sign up']);
        $funnel = collect($funnelSteps)->map(fn (int $count, string $stage): array => [
            'stage' => $stage,
            'count' => $count,
            'percent' => (int) round(($count / $funnelBase) * 100),
        ])->values()->all();

        $cohorts = $this->cohortsFor($start, $end);
        $retentionAvg = $cohorts === []
            ? 0.0
            : collect($cohorts)
                ->map(function (array $row): float {
                    if ($row['total'] === 0) {
                        return 0.0;
                    }
                    $laterMonths = array_slice($row['retained'], 1);
                    if ($laterMonths === []) {
                        return 0.0;
                    }
                    return (array_sum($laterMonths) / count($laterMonths)) / max(1, $row['total']) * 100;
                })
                ->avg();

        return [
            'range' => (string) $request->query('range', '30d'),
            'currency' => (string) config('services.stripe.currency', 'USD'),
            'totals' => [
                'signups' => $signups,
                'activated' => $activated,
                'activation_rate' => $signups > 0 ? round(($activated / $signups) * 100, 2) : 0.0,
                'paying' => $paying,
                'conversion_rate' => $signups > 0 ? round(($paying / $signups) * 100, 2) : 0.0,
            ],
            'timeseries' => [
                'signups' => $signupSeries,
                'activations' => $activationSeries,
                'reads' => $readSeries,
                'revenue' => $revenueSeries,
            ],
            'top_organizations' => $topOrgsRaw->map(fn (object $row): array => [
                'public_id' => (string) $row->public_id,
                'name' => (string) $row->organization_name,
                'reads' => (int) $row->reads,
                'earned' => number_format((float) $row->impact_amount, 2, '.', ''),
            ])->values()->all(),
            'top_articles' => $topArticlesRaw->map(fn (object $row): array => [
                'public_id' => (string) $row->public_id,
                'title' => (string) $row->title,
                'organization' => (string) ($row->organization ?? 'Unknown'),
                'reads' => (int) $row->reads,
            ])->values()->all(),
            'categories' => $categories,
            'funnel' => $funnel,
            'cohorts' => $cohorts,
            'retention_avg' => round((float) $retentionAvg, 2),
        ];
    }

    protected function rangeFromShorthand(Request $request): array
    {
        $end = now();
        $range = (string) $request->query('range', '30d');
        $start = match ($range) {
            '7d' => $end->copy()->subDays(6)->startOfDay(),
            '90d' => $end->copy()->subDays(89)->startOfDay(),
            '12m' => $end->copy()->subMonths(11)->startOfMonth(),
            default => $end->copy()->subDays(29)->startOfDay(),
        };

        return [$start, $end];
    }

    protected function dateSeries(array $buckets, $rows): array
    {
        return collect($buckets)->map(fn (array $bucket): array => [
            'date' => $bucket['bucket'],
            'count' => (int) ($rows[$bucket['bucket']] ?? 0),
        ])->all();
    }

    protected function dateAmountSeries(array $buckets, $rows): array
    {
        return collect($buckets)->map(fn (array $bucket): array => [
            'date' => $bucket['bucket'],
            'amount' => round((float) ($rows[$bucket['bucket']] ?? 0), 2),
        ])->all();
    }

    protected function cohortsFor(Carbon $start, Carbon $end): array
    {
        $cohortExpr = $this->periodExpression('users.created_at', 'month');
        $activityExpr = $this->periodExpression('article_reads.created_at', 'month');

        $cohortRows = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$cohortExpr} as cohort_month, COUNT(*) as cohort_size")
            ->groupBy('cohort_month')
            ->orderBy('cohort_month')
            ->get();

        if ($cohortRows->isEmpty()) {
            return [];
        }

        $activityRows = DB::table('users')
            ->join('article_reads', 'article_reads.user_id', '=', 'users.id')
            ->whereBetween('users.created_at', [$start, $end])
            ->selectRaw("{$cohortExpr} as cohort_month, {$activityExpr} as activity_month, COUNT(DISTINCT users.id) as active")
            ->groupBy('cohort_month', 'activity_month')
            ->get();

        return $cohortRows->map(function (object $cohort) use ($activityRows): array {
            $cohortMonth = (string) $cohort->cohort_month;
            $size = (int) $cohort->cohort_size;
            $retained = [];
            for ($offset = 0; $offset < 4; $offset++) {
                $activityMonth = Carbon::parse($cohortMonth.'-01')->addMonthsNoOverflow($offset)->format('Y-m');
                $match = $activityRows->first(fn (object $row): bool => $row->cohort_month === $cohortMonth && $row->activity_month === $activityMonth);
                $retained[] = $match ? (int) $match->active : 0;
            }

            return [
                'cohort' => $cohortMonth,
                'total' => $size,
                'retained' => $retained,
            ];
        })->values()->all();
    }

    public function overview(Request $request): array
    {
        return $this->cached('overview', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            [$previousStart, $previousEnd] = $this->previousRange($start, $end);

            $current = $this->overviewTotals($start, $end);
            $previous = $request->boolean('compare')
                ? $this->overviewTotals($previousStart, $previousEnd)
                : null;

            return [
                'period' => $this->periodPayload($request, $start, $end, $previousStart, $previousEnd),
                'metrics' => $current,
                'comparison' => $previous,
            ];
        });
    }

    public function timeseries(Request $request): array
    {
        return $this->cached('timeseries', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $interval = $this->interval($request);
            $buckets = $this->buckets($start, $end, $interval);

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'interval' => $interval,
                'series' => [
                    'user_signups' => $this->seriesFromRows($buckets, $this->countRows('users', 'created_at', $start, $end, $interval)),
                    'active_subscriptions' => $this->activeSubscriptionSeries($buckets, $interval),
                    'article_reads' => $this->seriesFromRows($buckets, $this->countRows('article_reads', 'created_at', $start, $end, $interval)),
                    'revenue' => $this->seriesFromRows($buckets, $this->sumRows('payments', 'paid_at', 'amount', $start, $end, $interval), true),
                    'impact_amount' => $this->seriesFromRows($buckets, $this->sumRows('impact_transactions', 'created_at', 'amount', $start, $end, $interval), true),
                ],
            ];
        });
    }

    public function topOrganizations(Request $request): array
    {
        return $this->cached('top-organizations', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $limit = $this->limit($request);

            $rows = DB::table('organization_profiles')
                ->leftJoin('articles', 'articles.organization_profile_id', '=', 'organization_profiles.id')
                ->leftJoin('article_reads', function ($join) use ($start, $end): void {
                    $join->on('article_reads.article_id', '=', 'articles.id')
                        ->whereBetween('article_reads.created_at', [$start, $end]);
                })
                ->leftJoin('impact_transactions', function ($join) use ($start, $end): void {
                    $join->on('impact_transactions.organization_profile_id', '=', 'organization_profiles.id')
                        ->whereBetween('impact_transactions.created_at', [$start, $end]);
                })
                ->leftJoin('payout_items', 'payout_items.organization_profile_id', '=', 'organization_profiles.id')
                ->selectRaw('organization_profiles.public_id, organization_profiles.organization_name')
                ->selectRaw('COUNT(DISTINCT article_reads.id) as reads')
                ->selectRaw('COALESCE(SUM(DISTINCT impact_transactions.amount), 0) as impact_amount')
                ->selectRaw('COALESCE(SUM(DISTINCT payout_items.payout_amount), 0) as payout_amount')
                ->selectRaw("COUNT(DISTINCT CASE WHEN articles.status = 'published' THEN articles.id END) as published_articles")
                ->groupBy('organization_profiles.id', 'organization_profiles.public_id', 'organization_profiles.organization_name')
                ->orderByDesc('reads')
                ->limit($limit)
                ->get();

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'organizations' => $rows->map(fn (object $row): array => [
                    'organization_public_id' => $row->public_id,
                    'organization_name' => $row->organization_name,
                    'reads' => (int) $row->reads,
                    'impact_generated' => $this->money($this->decimalToCents((string) $row->impact_amount)),
                    'payout_amount' => $this->money($this->decimalToCents((string) $row->payout_amount)),
                    'published_articles' => (int) $row->published_articles,
                ])->all(),
            ];
        });
    }

    public function topArticles(Request $request): array
    {
        return $this->cached('top-articles', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $limit = $this->limit($request);

            $rows = DB::table('articles')
                ->leftJoin('article_reads', function ($join) use ($start, $end): void {
                    $join->on('article_reads.article_id', '=', 'articles.id')
                        ->whereBetween('article_reads.created_at', [$start, $end]);
                })
                ->leftJoin('impact_transactions', function ($join) use ($start, $end): void {
                    $join->on('impact_transactions.article_id', '=', 'articles.id')
                        ->whereBetween('impact_transactions.created_at', [$start, $end]);
                })
                ->selectRaw('articles.public_id, articles.title, articles.category')
                ->selectRaw('COUNT(DISTINCT article_reads.id) as reads')
                ->selectRaw('COUNT(DISTINCT article_reads.user_id) as unique_readers')
                ->selectRaw('COALESCE(SUM(article_reads.reading_seconds), 0) as reading_seconds')
                ->selectRaw('COALESCE(SUM(article_reads.points_earned), 0) as points_generated')
                ->selectRaw('COALESCE(SUM(DISTINCT impact_transactions.amount), 0) as impact_generated')
                ->groupBy('articles.id', 'articles.public_id', 'articles.title', 'articles.category')
                ->orderByDesc('reads')
                ->limit($limit)
                ->get();

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'articles' => $rows->map(fn (object $row): array => [
                    'article_public_id' => $row->public_id,
                    'title' => $row->title,
                    'category' => $row->category,
                    'reads' => (int) $row->reads,
                    'unique_readers' => (int) $row->unique_readers,
                    'reading_seconds' => (int) $row->reading_seconds,
                    'points_generated' => (int) $row->points_generated,
                    'impact_generated' => $this->money($this->decimalToCents((string) $row->impact_generated)),
                ])->all(),
            ];
        });
    }

    public function categories(Request $request): array
    {
        return $this->cached('categories', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $reads = DB::table('articles')
                ->join('article_reads', 'article_reads.article_id', '=', 'articles.id')
                ->whereBetween('article_reads.created_at', [$start, $end])
                ->groupBy('articles.category')
                ->selectRaw("COALESCE(articles.category, 'Uncategorized') as category, COUNT(article_reads.id) as reads")
                ->pluck('reads', 'category');
            $articles = DB::table('articles')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('category')
                ->selectRaw("COALESCE(category, 'Uncategorized') as category, COUNT(*) as articles_count")
                ->pluck('articles_count', 'category');
            $impact = DB::table('articles')
                ->join('impact_transactions', 'impact_transactions.article_id', '=', 'articles.id')
                ->whereBetween('impact_transactions.created_at', [$start, $end])
                ->groupBy('articles.category')
                ->selectRaw("COALESCE(articles.category, 'Uncategorized') as category, SUM(impact_transactions.amount) as amount")
                ->pluck('amount', 'category');

            $categories = collect($reads->keys())
                ->merge($articles->keys())
                ->merge($impact->keys())
                ->unique()
                ->values();

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'categories' => $categories->map(fn (string $category): array => [
                    'category' => $category,
                    'reads' => (int) ($reads[$category] ?? 0),
                    'articles' => (int) ($articles[$category] ?? 0),
                    'impact_amount' => $this->money($this->decimalToCents((string) ($impact[$category] ?? '0'))),
                ])->all(),
            ];
        });
    }

    public function funnel(Request $request): array
    {
        return $this->cached('funnel', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $steps = [
                'registration_started' => DB::table('users')->whereBetween('created_at', [$start, $end])->count(),
                'email_verified' => DB::table('users')->whereBetween('created_at', [$start, $end])->whereNotNull('email_verified_at')->count(),
                'payment_completed' => DB::table('payments')->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->distinct('user_id')->count('user_id'),
                'first_login' => DB::table('users')->whereBetween('created_at', [$start, $end])->whereNotNull('last_login_at')->count(),
                'phone_mfa_completed' => DB::table('users')->whereBetween('created_at', [$start, $end])->whereNotNull('first_login_mfa_completed_at')->count(),
                'first_article_read' => DB::table('article_reads')
                    ->join('users', 'users.id', '=', 'article_reads.user_id')
                    ->whereBetween('users.created_at', [$start, $end])
                    ->distinct('article_reads.user_id')
                    ->count('article_reads.user_id'),
            ];

            $base = max(1, (int) $steps['registration_started']);

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'steps' => collect($steps)->map(fn (int $count, string $step): array => [
                    'key' => $step,
                    'count' => $count,
                    'conversion_from_registration_percent' => round(($count / $base) * 100, 2),
                ])->values()->all(),
            ];
        });
    }

    public function cohorts(Request $request): array
    {
        return $this->cached('cohorts', $request, function () use ($request): array {
            [$start, $end] = $this->range($request);
            $cohortExpression = $this->periodExpression('users.created_at', 'month');
            $activityExpression = $this->periodExpression('article_reads.created_at', 'month');

            $cohorts = DB::table('users')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("{$cohortExpression} as cohort_month, COUNT(*) as cohort_size")
                ->groupBy('cohort_month')
                ->orderBy('cohort_month')
                ->get()
                ->keyBy('cohort_month');

            $activity = DB::table('users')
                ->join('article_reads', 'article_reads.user_id', '=', 'users.id')
                ->whereBetween('users.created_at', [$start, $end])
                ->selectRaw("{$cohortExpression} as cohort_month, {$activityExpression} as activity_month, COUNT(DISTINCT users.id) as active_readers")
                ->groupBy('cohort_month', 'activity_month')
                ->orderBy('cohort_month')
                ->orderBy('activity_month')
                ->get();

            return [
                'period' => $this->periodPayload($request, $start, $end),
                'cohorts' => $cohorts->map(function (object $cohort, string $month) use ($activity): array {
                    $size = max(1, (int) $cohort->cohort_size);

                    return [
                        'cohort_month' => $month,
                        'cohort_size' => (int) $cohort->cohort_size,
                        'retention' => $activity
                            ->where('cohort_month', $month)
                            ->map(function (object $row) use ($month, $size): array {
                                $offset = Carbon::parse($month.'-01')->diffInMonths(Carbon::parse($row->activity_month.'-01'));

                                return [
                                    'month_offset' => $offset,
                                    'activity_month' => $row->activity_month,
                                    'active_readers' => (int) $row->active_readers,
                                    'retention_percent' => round(((int) $row->active_readers / $size) * 100, 2),
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })->values()->all(),
            ];
        });
    }

    protected function overviewTotals(Carbon $start, Carbon $end): array
    {
        return [
            'signups' => DB::table('users')->whereBetween('created_at', [$start, $end])->count(),
            'article_reads' => DB::table('article_reads')->whereBetween('created_at', [$start, $end])->count(),
            'gross_revenue' => $this->money($this->decimalToCents((string) DB::table('payments')->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('amount'))),
            'net_revenue' => $this->money($this->decimalToCents((string) DB::table('payments')->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('net_amount'))),
            'impact_amount' => $this->money($this->decimalToCents((string) DB::table('impact_transactions')->whereBetween('created_at', [$start, $end])->sum('amount'))),
            'published_articles' => DB::table('articles')->where('status', 'published')->whereBetween('published_at', [$start, $end])->count(),
        ];
    }

    protected function countRows(string $table, string $column, Carbon $start, Carbon $end, string $interval)
    {
        $expression = $this->periodExpression($column, $interval);

        return DB::table($table)
            ->selectRaw("{$expression} as bucket, COUNT(*) as value")
            ->whereBetween($column, [$start, $end])
            ->groupBy('bucket')
            ->pluck('value', 'bucket');
    }

    protected function sumRows(string $table, string $dateColumn, string $sumColumn, Carbon $start, Carbon $end, string $interval)
    {
        $expression = $this->periodExpression($dateColumn, $interval);

        return DB::table($table)
            ->selectRaw("{$expression} as bucket, SUM({$sumColumn}) as value")
            ->whereBetween($dateColumn, [$start, $end])
            ->when($table === 'payments', fn ($query) => $query->where('status', 'paid'))
            ->groupBy('bucket')
            ->pluck('value', 'bucket');
    }

    protected function activeSubscriptionSeries(array $buckets, string $interval): array
    {
        return collect($buckets)->map(function (array $bucket) use ($interval): array {
            $end = $this->bucketEnd($bucket['bucket'], $interval);

            return [
                'bucket' => $bucket['bucket'],
                'value' => DB::table('subscriptions')
                    ->where('status', 'active')
                    ->where('started_at', '<=', $end)
                    ->where('expires_at', '>', $end)
                    ->distinct('user_id')
                    ->count('user_id'),
            ];
        })->all();
    }

    protected function seriesFromRows(array $buckets, $rows, bool $money = false): array
    {
        return collect($buckets)->map(fn (array $bucket): array => [
            'bucket' => $bucket['bucket'],
            'value' => $money
                ? $this->money($this->decimalToCents((string) ($rows[$bucket['bucket']] ?? '0')))
                : (int) ($rows[$bucket['bucket']] ?? 0),
        ])->all();
    }

    protected function buckets(Carbon $start, Carbon $end, string $interval): array
    {
        $cursor = match ($interval) {
            'month' => $start->copy()->startOfMonth(),
            'week' => $start->copy()->startOfWeek(),
            default => $start->copy()->startOfDay(),
        };
        $last = match ($interval) {
            'month' => $end->copy()->startOfMonth(),
            'week' => $end->copy()->startOfWeek(),
            default => $end->copy()->startOfDay(),
        };
        $buckets = [];

        while ($cursor <= $last) {
            $buckets[] = ['bucket' => $this->formatBucket($cursor, $interval)];
            match ($interval) {
                'month' => $cursor->addMonth(),
                'week' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    protected function bucketEnd(string $bucket, string $interval): Carbon
    {
        return match ($interval) {
            'month' => Carbon::parse($bucket.'-01')->endOfMonth(),
            'week' => Carbon::parse($bucket)->endOfWeek(),
            default => Carbon::parse($bucket)->endOfDay(),
        };
    }

    protected function periodExpression(string $column, string $interval): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return match ($interval) {
                'month' => "to_char({$column}, 'YYYY-MM')",
                'week' => "to_char(date_trunc('week', {$column}), 'YYYY-MM-DD')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            };
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return match ($interval) {
                'month' => "DATE_FORMAT({$column}, '%Y-%m')",
                'week' => "DATE_FORMAT(DATE_SUB({$column}, INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
                default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            };
        }

        return match ($interval) {
            'month' => "strftime('%Y-%m', {$column})",
            'week' => "date({$column}, 'weekday 1', '-7 days')",
            default => "date({$column})",
        };
    }

    protected function formatBucket(Carbon $date, string $interval): string
    {
        return $interval === 'month' ? $date->format('Y-m') : $date->toDateString();
    }

    protected function range(Request $request): array
    {
        $end = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now();
        $start = $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        return [$start, $end];
    }

    protected function previousRange(Carbon $start, Carbon $end): array
    {
        $days = $start->diffInDays($end) + 1;

        return [$start->copy()->subDays($days), $start->copy()->subSecond()];
    }

    protected function periodPayload(Request $request, Carbon $start, Carbon $end, ?Carbon $previousStart = null, ?Carbon $previousEnd = null): array
    {
        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'interval' => $this->interval($request),
            'previous_start' => $previousStart?->toDateString(),
            'previous_end' => $previousEnd?->toDateString(),
        ];
    }

    protected function interval(Request $request): string
    {
        return (string) $request->query('interval', 'day');
    }

    protected function limit(Request $request): int
    {
        return min(max((int) $request->query('limit', 10), 1), 50);
    }

    protected function cached(string $name, Request $request, callable $callback): array
    {
        $key = 'admin-analytics:'.$name.':'.md5(json_encode($request->query()));

        return Cache::remember($key, now()->addMinutes(5), $callback);
    }

    protected function decimalToCents(string $value): int
    {
        $normalized = trim($value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$dollars, $cents] = array_pad(explode('.', $normalized, 2), 2, '0');
        $cents = str_pad(substr(preg_replace('/\D/', '', $cents), 0, 2), 2, '0');
        $amount = (((int) preg_replace('/\D/', '', $dollars)) * 100) + (int) $cents;

        return $negative ? -$amount : $amount;
    }

    protected function money(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return ($negative ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
