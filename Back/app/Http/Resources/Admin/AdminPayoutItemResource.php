<?php

namespace App\Http\Resources\Admin;

use App\Models\PayoutItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class AdminPayoutItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PayoutItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'organization' => [
                'public_id' => $item->organizationProfile?->public_id,
                'name' => $item->organizationProfile?->organization_name,
                'stripe_connect_account_id' => $this->canViewPayoutIdentifiers($request) ? $item->organizationProfile?->stripe_connect_account_id : null,
                'payouts_enabled' => (bool) $item->organizationProfile?->payouts_enabled,
            ],
            'reads' => (int) $item->total_reads,
            'points' => (int) $item->total_points,
            'engagement_score' => number_format((float) $item->engagement_score, 4, '.', ''),
            'engagement_share' => $item->metadata['engagement_share'] ?? null,
            'payout_amount' => number_format((float) $item->payout_amount, 2, '.', ''),
            'stripe_transfer_id' => $this->canViewPayoutIdentifiers($request) ? $item->stripe_transfer_id : null,
            'transfer_status' => $item->transfer_status,
            'failure_reason' => $this->canViewPayoutIdentifiers($request) ? ($item->metadata['failure_reason'] ?? null) : null,
            'transferred_at' => $item->transferred_at?->toIso8601String(),
            'metadata' => $this->canViewPayoutIdentifiers($request) ? $item->metadata : null,
        ];
    }

    protected function canViewPayoutIdentifiers(Request $request): bool
    {
        if (! Schema::hasTable('permissions')) {
            return false;
        }

        return (bool) $request->user()?->canAny(['payouts.execute', 'payouts.generate']);
    }
}
