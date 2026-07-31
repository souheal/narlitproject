<?php

namespace App\Services\Organization;

use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\PayoutItem;
use Illuminate\Support\Carbon;

class OrganizationPayoutService
{
    public function overview(OrganizationProfile $organization): array
    {
        $currency = (string) config('services.stripe.currency', 'USD');

        $items = PayoutItem::query()
            ->with('batch:id,public_id,batch_month,status')
            ->where('organization_profile_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();

        $payouts = $items->map(fn (PayoutItem $item): array => $this->transformItem($item, $currency))->all();

        $totalEarnedAllTime = (float) ImpactTransaction::query()
            ->where('organization_profile_id', $organization->id)
            ->sum('amount');

        $pendingAmount = (float) $items
            ->filter(fn (PayoutItem $item): bool => in_array($item->transfer_status, ['pending', 'failed'], true)
                || $item->transfer_status === null)
            ->sum(fn (PayoutItem $item): float => (float) $item->payout_amount);

        $totalPaid = (float) $items
            ->filter(fn (PayoutItem $item): bool => $item->transfer_status === 'succeeded')
            ->sum(fn (PayoutItem $item): float => (float) $item->payout_amount);

        $lastPaid = $items
            ->first(fn (PayoutItem $item): bool => $item->transfer_status === 'succeeded');

        $stripeConnected = (bool) $organization->payouts_enabled
            && ! empty($organization->stripe_connect_account_id);

        $summary = [
            'total_earned_all_time' => $this->money($totalEarnedAllTime),
            'total_paid' => $this->money($totalPaid),
            'pending_amount' => $this->money($pendingAmount),
            'pending_payout' => $this->money($pendingAmount),
            'last_payout_amount' => $lastPaid ? $this->money((float) $lastPaid->payout_amount) : $this->money(0),
            'last_payout_at' => $lastPaid?->transferred_at?->toIso8601String(),
            'next_payout_date' => $this->nextPayoutDate(),
            'currency' => $currency,
            'stripe_connected' => $stripeConnected,
        ];

        $connect = [
            'connected' => ! empty($organization->stripe_connect_account_id),
            'charges_enabled' => (bool) $organization->charges_enabled,
            'payouts_enabled' => (bool) $organization->payouts_enabled,
            'details_submitted' => (bool) ($organization->metadata['stripe_details_submitted'] ?? $organization->charges_enabled),
            'requirements_due' => (array) ($organization->metadata['stripe_requirements_due'] ?? []),
        ];

        return [
            'payouts' => [
                'data' => $payouts,
                'current_page' => 1,
                'last_page' => 1,
                'total' => count($payouts),
            ],
            'summary' => $summary,
            'connect' => $connect,
        ];
    }

    protected function transformItem(PayoutItem $item, string $currency): array
    {
        $batch = $item->batch;
        $monthDate = $batch?->batch_month ? Carbon::parse($batch->batch_month) : null;
        $periodStart = $monthDate?->copy()->startOfMonth()->toDateString();
        $periodEnd = $monthDate?->copy()->endOfMonth()->toDateString();
        $batchMonth = $monthDate?->format('Y-m');

        $status = $this->normalizeStatus($item->transfer_status);

        return [
            'public_id' => $item->batch?->public_id ?? (string) $item->id,
            'batch_month' => $batchMonth,
            'amount' => $this->money((float) $item->payout_amount),
            'currency' => $currency,
            'status' => $status,
            'stripe_transfer_id' => $item->stripe_transfer_id,
            'transferred_at' => $item->transferred_at?->toIso8601String(),
            'paid_at' => $item->transferred_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'reads' => (int) $item->total_reads,
            'total_reads' => (int) $item->total_reads,
            'points' => (int) $item->total_points,
        ];
    }

    protected function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'succeeded' => 'paid',
            'processing' => 'processing',
            'failed' => 'failed',
            'on_hold' => 'on_hold',
            null => 'pending',
            default => $status,
        };
    }

    protected function nextPayoutDate(): string
    {
        // Convention: payouts run on the 1st of each month.
        return now()->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    protected function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
