<?php

namespace App\Http\Resources\Admin;

use App\Models\PayoutItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PayoutItem $item */
        $item = $this->resource;
        $currency = (string) config('services.stripe.currency', 'USD');

        return [
            'id' => $item->id,
            'public_id' => (string) $item->id,
            'organization' => [
                'public_id' => $item->organizationProfile?->public_id,
                'name' => $item->organizationProfile?->organization_name,
                'stripe_connect_account_id' => $item->organizationProfile?->stripe_connect_account_id,
                'payouts_enabled' => (bool) $item->organizationProfile?->payouts_enabled,
            ],
            'reads' => (int) $item->total_reads,
            'total_reads' => (int) $item->total_reads,
            'points' => (int) $item->total_points,
            'engagement_score' => number_format((float) $item->engagement_score, 4, '.', ''),
            'engagement_share' => $item->metadata['engagement_share'] ?? null,
            'payout_amount' => number_format((float) $item->payout_amount, 2, '.', ''),
            'amount' => number_format((float) $item->payout_amount, 2, '.', ''),
            'currency' => $currency,
            'stripe_transfer_id' => $item->stripe_transfer_id,
            'transfer_status' => $item->transfer_status,
            'status' => $this->deriveStatus($item->transfer_status),
            'failure_reason' => $item->metadata['failure_reason'] ?? null,
            'transferred_at' => $item->transferred_at?->toIso8601String(),
            'paid_at' => $item->transferred_at?->toIso8601String(),
            'metadata' => $item->metadata,
        ];
    }

    protected function deriveStatus(?string $transferStatus): string
    {
        return match ($transferStatus) {
            'succeeded' => 'paid',
            'failed' => 'failed',
            'pending' => 'pending',
            'canceled' => 'canceled',
            null => 'pending',
            default => (string) $transferStatus,
        };
    }
}
