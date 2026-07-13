<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class AdminSubscriptionRevenueService
{
    public function summary(Request $request): array
    {
        $end = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now();
        $start = $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : $end->copy()->subMonths(11)->startOfMonth();
        $previousStart = $start->copy()->subDays($start->diffInDays($end) + 1);
        $previousEnd = $start->copy()->subSecond();

        $activeSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get(['user_id', 'plan', 'amount']);

        $mrrCents = $activeSubscriptions->sum(fn (Subscription $subscription): int => $this->monthlyCents($subscription));
        $activeSubscribers = $activeSubscriptions->pluck('user_id')->unique()->count();
        $arpuCents = $activeSubscribers > 0 ? intdiv($mrrCents, $activeSubscribers) : 0;
        $subscribersAtPeriodStart = $this->subscribersAt($start);
        $canceledInPeriod = Subscription::query()
            ->where('status', 'canceled')
            ->whereBetween('canceled_at', [$start, $end])
            ->count();
        $churnRate = $subscribersAtPeriodStart > 0 ? round(($canceledInPeriod / $subscribersAtPeriodStart) * 100, 2) : 0.0;
        $ltvCents = $churnRate > 0 ? (int) round($arpuCents / ($churnRate / 100)) : 0;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'previous_start' => $previousStart->toDateString(),
                'previous_end' => $previousEnd->toDateString(),
            ],
            'kpis' => [
                'mrr' => $this->money($mrrCents),
                'arr' => $this->money($mrrCents * 12),
                'churn_rate' => $churnRate,
                'arpu' => $this->money($arpuCents),
                'estimated_ltv' => $this->money($ltvCents),
                'active_subscriptions' => Subscription::query()->where('status', 'active')->where('expires_at', '>', now())->count(),
                'past_due_subscriptions' => Subscription::query()->where('status', 'past_due')->count(),
                'canceled_subscriptions' => Subscription::query()->where('status', 'canceled')->count(),
            ],
            'metric_notes' => [
                'mrr' => 'Active subscriptions normalized to monthly cents; yearly plans are divided by 12.',
                'arr' => 'MRR multiplied by 12.',
                'arpu' => 'MRR divided by unique active subscribers.',
                'churn_rate' => 'Canceled subscriptions in period divided by active subscribers at period start.',
                'estimated_ltv' => 'Simplified LTV: ARPU divided by churn rate. Returns 0 when churn is 0.',
            ],
            'revenue_chart' => [
                'current' => $this->monthlyRevenueSeries($start, $end),
                'previous' => $this->monthlyRevenueSeries($previousStart, $previousEnd),
            ],
            'plan_breakdown' => $this->planBreakdown(),
        ];
    }

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Subscription::query()
            ->with('user')
            ->select('subscriptions.*')
            ->join('users', 'users.id', '=', 'subscriptions.user_id');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(min(max((int) $request->query('per_page', 15), 1), 100));
    }

    public function details(string $publicId): Subscription
    {
        $subscription = Subscription::query()
            ->with(['user', 'payments' => fn ($query) => $query->latest()])
            ->where('public_id', $publicId)
            ->first();

        if ($subscription === null) {
            throw new ApiException('Subscription was not found.', 404);
        }

        $subscription->setAttribute('admin_action_history', DB::table('admin_logs')
            ->join('users as admins', 'admins.id', '=', 'admin_logs.admin_id')
            ->where(function ($query) use ($subscription): void {
                $query->where(function ($nested) use ($subscription): void {
                    $nested->where('admin_logs.entity_type', 'subscription')
                        ->where('admin_logs.entity_id', $subscription->public_id);
                })->orWhere(function ($nested) use ($subscription): void {
                    $nested->where('admin_logs.entity_type', 'payment')
                        ->whereIn('admin_logs.entity_id', $subscription->payments->pluck('public_id'));
                });
            })
            ->latest('admin_logs.created_at')
            ->limit(20)
            ->get(['admin_logs.action', 'admin_logs.metadata', 'admin_logs.created_at', 'admins.full_name as admin_name']));

        return $subscription;
    }

    public function cancel(User $admin, string $publicId, Request $request): Subscription
    {
        $subscription = Subscription::query()->with('user')->where('public_id', $publicId)->first();

        if ($subscription === null) {
            throw new ApiException('Subscription was not found.', 404);
        }

        if ($subscription->status === 'canceled') {
            return $subscription;
        }

        $stripeSubscription = $this->cancelInStripe($subscription);

        return DB::transaction(function () use ($admin, $subscription, $stripeSubscription, $request): Subscription {
            $subscription->forceFill([
                'status' => 'canceled',
                'canceled_at' => isset($stripeSubscription->canceled_at) && $stripeSubscription->canceled_at
                    ? Carbon::createFromTimestampUTC((int) $stripeSubscription->canceled_at)
                    : now(),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'admin_cancel' => [
                        'stripe_status' => (string) ($stripeSubscription->status ?? 'canceled'),
                        'reason' => $request->input('reason'),
                        'canceled_by' => $admin->public_id,
                        'canceled_at' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();

            $subscription->user?->forceFill(['is_active' => false])->save();
            $this->log($admin, 'subscription', $subscription->public_id, 'subscription.canceled', $request, [
                'reason' => $request->input('reason'),
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);

            return $subscription->refresh();
        }, 3);
    }

    public function refund(User $admin, string $publicId, string $reason, Request $request): Payment
    {
        $payment = Payment::query()->with('subscription')->where('public_id', $publicId)->first();

        if ($payment === null) {
            throw new ApiException('Payment was not found.', 404);
        }

        if ($payment->status === 'refunded' || $payment->refunded_at !== null) {
            return $payment;
        }

        if ($payment->status !== 'paid') {
            throw new ApiException('Only paid payments can be refunded.', 422);
        }

        $refund = $this->refundInStripe($payment, $reason);

        return DB::transaction(function () use ($admin, $payment, $refund, $reason, $request): Payment {
            $payment->forceFill([
                'status' => 'refunded',
                'refunded_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'admin_refund' => [
                        'stripe_refund_id' => (string) ($refund->id ?? 'fake_refund_'.$payment->id),
                        'reason' => $reason,
                        'refunded_by' => $admin->public_id,
                        'refunded_at' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();

            $this->log($admin, 'payment', $payment->public_id, 'payment.refunded', $request, [
                'reason' => $reason,
                'stripe_payment_intent' => $payment->stripe_payment_intent,
                'stripe_refund_id' => (string) ($refund->id ?? null),
            ]);

            return $payment->refresh();
        }, 3);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('users.full_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('subscriptions.stripe_customer_id', 'like', "%{$search}%")
                    ->orWhere('subscriptions.stripe_subscription_id', 'like', "%{$search}%");
            });
        }

        foreach (['plan', 'status', 'currency'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("subscriptions.{$filter}", $request->query($filter));
            }
        }

        if ($request->filled('started_from')) {
            $query->whereDate('subscriptions.started_at', '>=', $request->query('started_from'));
        }

        if ($request->filled('started_to')) {
            $query->whereDate('subscriptions.started_at', '<=', $request->query('started_to'));
        }
    }

    protected function applySorting(Builder $query, Request $request): void
    {
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ((string) $request->query('sort', 'date')) {
            'amount' => $query->orderBy('subscriptions.amount', $direction),
            'subscriber' => $query->orderBy('users.full_name', $direction),
            'status' => $query->orderBy('subscriptions.status', $direction),
            'renewal' => $query->orderBy('subscriptions.expires_at', $direction),
            default => $query->orderBy('subscriptions.started_at', $direction),
        };

        $query->orderBy('subscriptions.id', 'desc');
    }

    protected function monthlyRevenueSeries(Carbon $start, Carbon $end): array
    {
        $monthExpression = match (DB::getDriverName()) {
            'pgsql' => "to_char(paid_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(paid_at, '%Y-%m')",
            default => "strftime('%Y-%m', paid_at)",
        };

        $rows = Payment::query()
            ->selectRaw("{$monthExpression} as month, SUM(amount) as gross_amount, SUM(net_amount) as net_amount, SUM(stripe_fee) as stripe_fee")
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $series = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $month = $cursor->format('Y-m');
            $row = $rows->get($month);
            $series[] = [
                'month' => $month,
                'gross_revenue' => $this->money($this->decimalToCents((string) ($row->gross_amount ?? '0'))),
                'net_revenue' => $this->money($this->decimalToCents((string) ($row->net_amount ?? '0'))),
                'stripe_fees' => $this->money($this->decimalToCents((string) ($row->stripe_fee ?? '0'))),
            ];
            $cursor->addMonth();
        }

        return $series;
    }

    protected function planBreakdown(): array
    {
        $rows = Subscription::query()
            ->selectRaw('plan, COUNT(*) as subscriptions_count, COUNT(DISTINCT user_id) as subscribers_count, SUM(amount) as revenue')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->groupBy('plan')
            ->get();

        return [
            'plans' => $rows->map(fn (object $row): array => [
                'plan' => $row->plan,
                'subscriptions_count' => (int) $row->subscriptions_count,
                'subscribers_count' => (int) $row->subscribers_count,
                'revenue' => $this->money($this->decimalToCents((string) ($row->revenue ?? '0'))),
            ])->values()->all(),
            'monthly_plan_count' => (int) ($rows->firstWhere('plan', 'monthly')?->subscriptions_count ?? 0),
            'yearly_plan_count' => (int) ($rows->firstWhere('plan', 'yearly')?->subscriptions_count ?? 0),
            'founding_member_plan_count' => (int) ($rows->firstWhere('plan', 'founding_member')?->subscriptions_count ?? 0),
        ];
    }

    protected function subscribersAt(Carbon $date): int
    {
        return Subscription::query()
            ->where('started_at', '<=', $date)
            ->where('expires_at', '>', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->where('status', 'active')
                    ->orWhere('canceled_at', '>', $date);
            })
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function cancelInStripe(Subscription $subscription): object
    {
        if ((bool) config('services.stripe.fake_checkout', false)) {
            return (object) [
                'id' => $subscription->stripe_subscription_id,
                'status' => 'canceled',
                'canceled_at' => now()->timestamp,
            ];
        }

        return $this->client()->subscriptions->cancel($subscription->stripe_subscription_id, []);
    }

    protected function refundInStripe(Payment $payment, string $reason): object
    {
        if ((bool) config('services.stripe.fake_checkout', false)) {
            return (object) [
                'id' => 'fake_refund_'.$payment->id,
                'status' => 'succeeded',
            ];
        }

        return $this->client()->refunds->create([
            'payment_intent' => $payment->stripe_payment_intent,
            'reason' => 'requested_by_customer',
            'metadata' => [
                'admin_reason' => $reason,
                'payment_public_id' => $payment->public_id,
            ],
        ], [
            'idempotency_key' => "admin-refund-{$payment->public_id}",
        ]);
    }

    protected function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '' || $secret === 'sk_test_replace_me' || $secret === 'sk_live_replace_me') {
            throw new ApiException('Stripe is not configured.', 500);
        }

        return new StripeClient($secret);
    }

    protected function monthlyCents(Subscription $subscription): int
    {
        $cents = $this->decimalToCents((string) $subscription->amount);

        return $subscription->plan === 'yearly' ? intdiv($cents, 12) : $cents;
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
        $dollars = intdiv($absolute, 100);
        $minor = $absolute % 100;

        return ($negative ? '-' : '').$dollars.'.'.str_pad((string) $minor, 2, '0', STR_PAD_LEFT);
    }

    protected function log(User $admin, string $entityType, string $entityId, string $action, Request $request, array $metadata = []): void
    {
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
