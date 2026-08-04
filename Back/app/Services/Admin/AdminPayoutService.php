<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Jobs\ExecutePayoutBatchJob;
use App\Models\OrganizationProfile;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class AdminPayoutService
{
    public function summary(): array
    {
        $currentMonth = now()->startOfMonth();
        $preview = $this->calculate($currentMonth);

        return [
            'current_payout_pool' => $this->money($preview['pool_cents']),
            'pending_payout_amount' => $this->sumItemsByStatuses(['pending']),
            'completed_payout_amount' => $this->sumItemsByStatuses(['completed']),
            'failed_payout_amount' => $this->sumItemsByStatuses(['failed']),
            'organizations_awaiting_payout' => PayoutItem::query()
                ->where('transfer_status', 'pending')
                ->distinct('organization_profile_id')
                ->count('organization_profile_id'),
            'organizations_missing_stripe_connect_setup' => OrganizationProfile::query()
                ->where('verification_status', 'approved')
                ->where(function (Builder $query): void {
                    $query->whereNull('stripe_connect_account_id')
                        ->orWhere('payouts_enabled', false);
                })
                ->count(),
            'formula' => $preview['formula'],
        ];
    }

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = PayoutBatch::query();

        if ($request->filled('month')) {
            $query->whereDate('batch_month', Carbon::createFromFormat('Y-m', (string) $request->query('month'))->startOfMonth());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('organization')) {
            $query->whereHas('items.organizationProfile', fn (Builder $organization): Builder => $organization
                ->where('public_id', $request->query('organization')));
        }

        if ($request->filled('transfer_status')) {
            $query->whereHas('items', fn (Builder $item): Builder => $item->where('transfer_status', $request->query('transfer_status')));
        }

        return $query
            ->latest('batch_month')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));
    }

    public function details(string $publicId): PayoutBatch
    {
        $batch = PayoutBatch::query()
            ->with(['items.organizationProfile'])
            ->where('public_id', $publicId)
            ->first();

        if ($batch === null) {
            throw new ApiException('Payout batch was not found.', 404);
        }

        $batch->setAttribute('admin_action_history', DB::table('admin_logs')
            ->join('users as admins', 'admins.id', '=', 'admin_logs.admin_id')
            ->where('admin_logs.entity_type', 'payout_batch')
            ->where('admin_logs.entity_id', $batch->public_id)
            ->latest('admin_logs.created_at')
            ->limit(20)
            ->get(['admin_logs.action', 'admin_logs.metadata', 'admin_logs.created_at', 'admins.full_name as admin_name']));

        return $batch;
    }

    public function generate(User $admin, string $month, bool $preview, Request $request): array
    {
        $batchMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $calculation = $this->calculate($batchMonth);

        if ($preview) {
            return [
                'preview' => true,
                'calculation' => $calculation,
            ];
        }

        return DB::transaction(function () use ($admin, $batchMonth, $calculation, $request): array {
            if (PayoutBatch::query()->whereDate('batch_month', $batchMonth)->lockForUpdate()->exists()) {
                throw new ApiException('A payout batch already exists for this month.', 409);
            }

            $batch = PayoutBatch::query()->create([
                'public_id' => (string) Str::uuid(),
                'batch_month' => $batchMonth->toDateString(),
                'total_pool' => $this->money($calculation['pool_cents']),
                'total_distributed' => $this->money($calculation['distributed_cents']),
                'total_organizations' => count($calculation['items']),
                'status' => 'pending',
                'metadata' => [
                    'formula' => $calculation['formula'],
                    'inputs' => $calculation['inputs'],
                    'generated_by' => $admin->public_id,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

            foreach ($calculation['items'] as $item) {
                PayoutItem::query()->create([
                    'payout_batch_id' => $batch->id,
                    'organization_profile_id' => $item['organization_profile_id'],
                    'engagement_score' => $item['engagement_score'],
                    'payout_amount' => $item['payout_amount'],
                    'total_reads' => $item['total_reads'],
                    'total_points' => $item['total_points'],
                    'transfer_status' => 'pending',
                    'metadata' => [
                        'engagement_share' => $item['engagement_share'],
                        'calculation' => $item,
                    ],
                ]);
            }

            $this->log($admin, 'payout_batch', $batch->public_id, 'payout_batch.generated', $request, [
                'batch_month' => $batchMonth->toDateString(),
                'total_pool' => $batch->total_pool,
            ]);

            return [
                'preview' => false,
                'batch' => $batch->refresh()->load('items.organizationProfile'),
            ];
        }, 3);
    }

    public function execute(User $admin, string $publicId, Request $request): PayoutBatch
    {
        $batch = PayoutBatch::query()->where('public_id', $publicId)->first();

        if ($batch === null) {
            throw new ApiException('Payout batch was not found.', 404);
        }

        if (! in_array($batch->status, ['pending', 'failed'], true)) {
            throw new ApiException('Only pending or failed payout batches can be executed.', 422);
        }

        DB::transaction(function () use ($admin, $batch, $request): void {
            $batch->forceFill([
                'status' => 'processing',
                'metadata' => array_merge($batch->metadata ?? [], [
                    'execution_idempotency_key' => $request->attributes->get('stripe_idempotency_key'),
                ]),
            ])->save();
            $this->log($admin, 'payout_batch', $batch->public_id, 'payout_batch.execution_queued', $request);
        }, 3);

        ExecutePayoutBatchJob::dispatch($batch->id);

        return $batch->refresh()->load('items.organizationProfile');
    }

    public function retryItem(User $admin, int $id, Request $request): PayoutItem
    {
        $item = PayoutItem::query()->with('batch')->find($id);

        if ($item === null) {
            throw new ApiException('Payout item was not found.', 404);
        }

        if ($item->transfer_status !== 'failed') {
            throw new ApiException('Only failed payout items can be retried.', 422);
        }

        DB::transaction(function () use ($admin, $item, $request): void {
            $item->forceFill([
                'transfer_status' => 'pending',
                'metadata' => array_merge($item->metadata ?? [], [
                    'retry_requested_at' => now()->toIso8601String(),
                    'failure_reason' => null,
                ]),
            ])->save();

            $item->batch->forceFill(['status' => 'processing'])->save();
            $this->log($admin, 'payout_item', (string) $item->id, 'payout_item.retry_queued', $request);
        }, 3);

        ExecutePayoutBatchJob::dispatch($item->payout_batch_id);

        return $item->refresh()->load('organizationProfile');
    }

    public function cancel(User $admin, string $publicId, Request $request): PayoutBatch
    {
        $batch = PayoutBatch::query()->with('items')->where('public_id', $publicId)->first();

        if ($batch === null) {
            throw new ApiException('Payout batch was not found.', 404);
        }

        if (! in_array($batch->status, ['pending', 'failed'], true)) {
            throw new ApiException('Only unprocessed payout batches can be canceled.', 422);
        }

        if ($batch->items->contains(fn (PayoutItem $item): bool => $item->transfer_status === 'completed' || $item->stripe_transfer_id !== null)) {
            throw new ApiException('This payout batch has transfer records and cannot be canceled.', 422);
        }

        return DB::transaction(function () use ($admin, $batch, $request): PayoutBatch {
            $batch->items()->update([
                'transfer_status' => 'canceled',
                'updated_at' => now(),
            ]);
            $batch->forceFill([
                'status' => 'canceled',
                'processed_at' => now(),
                'metadata' => array_merge($batch->metadata ?? [], [
                    'canceled_by' => $admin->public_id,
                    'canceled_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $this->log($admin, 'payout_batch', $batch->public_id, 'payout_batch.canceled', $request);

            return $batch->refresh()->load('items.organizationProfile');
        }, 3);
    }

    public function processBatch(int $batchId): void
    {
        $batch = PayoutBatch::query()->with('items.organizationProfile')->find($batchId);

        if ($batch === null || $batch->status === 'canceled') {
            return;
        }

        foreach ($batch->items as $item) {
            if ($item->transfer_status === 'pending') {
                $this->processItem($item);
            }
        }

        $batch->refresh()->load('items');
        $failed = $batch->items->where('transfer_status', 'failed')->count();
        $pending = $batch->items->where('transfer_status', 'pending')->count();
        $completed = $batch->items->where('transfer_status', 'completed')->count();

        $batch->forceFill([
            'status' => $failed > 0 ? 'failed' : ($pending > 0 ? 'processing' : 'completed'),
            'processed_at' => $pending === 0 ? now() : null,
            'total_distributed' => $this->money($batch->items->where('transfer_status', 'completed')->sum(fn (PayoutItem $item): int => $this->decimalToCents((string) $item->payout_amount))),
            'metadata' => array_merge($batch->metadata ?? [], [
                'execution_summary' => [
                    'completed' => $completed,
                    'failed' => $failed,
                    'pending' => $pending,
                    'executed_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    protected function processItem(PayoutItem $item): void
    {
        $item = PayoutItem::query()->with(['batch', 'organizationProfile'])->whereKey($item->id)->lockForUpdate()->first();

        if ($item === null || $item->transfer_status !== 'pending') {
            return;
        }

        if ($item->stripe_transfer_id !== null) {
            $item->forceFill(['transfer_status' => 'completed', 'transferred_at' => $item->transferred_at ?? now()])->save();

            return;
        }

        $organization = $item->organizationProfile;

        if ($organization?->stripe_connect_account_id === null || ! $organization->payouts_enabled) {
            $this->failItem($item, 'Organization is missing Stripe Connect payout setup.');

            return;
        }

        if (! (bool) config('services.stripe.fake_checkout', false) && ! (bool) config('services.stripe.transfers_enabled', false)) {
            $this->failItem($item, 'Stripe transfers are disabled for this environment.');

            return;
        }

        try {
            $transfer = (bool) config('services.stripe.fake_checkout', false)
                ? (object) ['id' => 'fake_transfer_'.$item->id, 'status' => 'paid']
                : $this->client()->transfers->create([
                    'amount' => $this->decimalToCents((string) $item->payout_amount),
                    'currency' => strtolower((string) config('services.stripe.currency', 'USD')),
                    'destination' => $organization->stripe_connect_account_id,
                    'metadata' => [
                        'payout_item_id' => (string) $item->id,
                        'payout_batch_id' => (string) $item->payout_batch_id,
                    ],
                ], [
                    'idempotency_key' => $this->payoutTransferIdempotencyKey($item),
                ]);

            $item->forceFill([
                'stripe_transfer_id' => (string) $transfer->id,
                'transfer_status' => 'completed',
                'transferred_at' => now(),
                'metadata' => array_merge($item->metadata ?? [], [
                    'stripe_transfer_status' => (string) ($transfer->status ?? 'paid'),
                    'failure_reason' => null,
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            $this->failItem($item, $exception->getMessage());
        }
    }

    protected function failItem(PayoutItem $item, string $reason): void
    {
        $item->forceFill([
            'transfer_status' => 'failed',
            'metadata' => array_merge($item->metadata ?? [], [
                'failure_reason' => $reason,
                'failed_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    protected function payoutTransferIdempotencyKey(PayoutItem $item): string
    {
        $executionKey = $item->batch?->metadata['execution_idempotency_key'] ?? null;

        if (is_string($executionKey) && $executionKey !== '') {
            return "{$executionKey}-item-{$item->id}";
        }

        return "payout-item-{$item->id}";
    }

    protected function calculate(Carbon $batchMonth): array
    {
        $start = $batchMonth->copy()->startOfMonth();
        $end = $batchMonth->copy()->endOfMonth();
        $nonprofitShare = (int) config('services.impact.nonprofit_share_percent', 33);
        $netRevenueCents = $this->decimalToCents((string) DB::table('payments')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('net_amount'));
        $poolCents = intdiv($netRevenueCents * $nonprofitShare, 100);

        $rows = DB::table('article_reads')
            ->join('articles', 'articles.id', '=', 'article_reads.article_id')
            ->join('organization_profiles', 'organization_profiles.id', '=', 'articles.organization_profile_id')
            ->where('article_reads.counted_for_payout', true)
            ->whereBetween('article_reads.created_at', [$start, $end])
            ->where('organization_profiles.verification_status', 'approved')
            ->groupBy('organization_profiles.id', 'organization_profiles.public_id', 'organization_profiles.organization_name')
            ->selectRaw('organization_profiles.id as organization_profile_id, organization_profiles.public_id, organization_profiles.organization_name, COUNT(article_reads.id) as total_reads, SUM(article_reads.points_earned) as total_points')
            ->get();

        $scores = $rows->map(function (object $row): array {
            $points = (int) ($row->total_points ?? 0);
            $reads = (int) ($row->total_reads ?? 0);

            return [
                'organization_profile_id' => (int) $row->organization_profile_id,
                'organization_public_id' => $row->public_id,
                'organization_name' => $row->organization_name,
                'total_reads' => $reads,
                'total_points' => $points,
                'score_cents' => max($points, $reads),
            ];
        });

        $totalScore = max(0, (int) $scores->sum('score_cents'));
        $allocated = 0;
        $items = [];

        foreach ($scores as $row) {
            $amountCents = $totalScore > 0 ? intdiv($poolCents * $row['score_cents'], $totalScore) : 0;
            $allocated += $amountCents;
            $items[] = array_merge($row, [
                'engagement_score' => number_format($row['score_cents'], 4, '.', ''),
                'engagement_share' => $totalScore > 0 ? round($row['score_cents'] / $totalScore, 6) : 0,
                'payout_amount' => $this->money($amountCents),
                'payout_cents' => $amountCents,
            ]);
        }

        if ($items !== [] && $poolCents > $allocated) {
            $items[0]['payout_cents'] += $poolCents - $allocated;
            $items[0]['payout_amount'] = $this->money($items[0]['payout_cents']);
            $allocated = $poolCents;
        }

        return [
            'batch_month' => $start->toDateString(),
            'pool_cents' => $poolCents,
            'pool_amount' => $this->money($poolCents),
            'distributed_cents' => $allocated,
            'distributed_amount' => $this->money($allocated),
            'items' => $items,
            'inputs' => [
                'net_collected_subscription_revenue' => $this->money($netRevenueCents),
                'nonprofit_share_percent' => $nonprofitShare,
                'valid_counted_reads' => (int) $rows->sum('total_reads'),
                'total_engagement_score' => $totalScore,
            ],
            'formula' => 'pool = paid subscription net revenue for month * nonprofit_share_percent; organization score = max(total_points, total_counted_reads); payout = pool * organization_score / total_score, rounded down to cents with remainder assigned to the first ranked organization.',
        ];
    }

    protected function sumItemsByStatuses(array $statuses): string
    {
        return $this->money($this->decimalToCents((string) PayoutItem::query()
            ->whereIn('transfer_status', $statuses)
            ->sum('payout_amount')));
    }

    protected function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '' || $secret === 'sk_test_replace_me' || $secret === 'sk_live_replace_me') {
            throw new ApiException('Stripe is not configured.', 500);
        }

        return new StripeClient($secret);
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
