<?php

namespace App\Http\Resources\Admin;

use App\Models\PayoutBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PayoutBatch $batch */
        $batch = $this->resource;
        $monthStart = $batch->batch_month?->copy()->startOfMonth();
        $monthEnd = $batch->batch_month?->copy()->endOfMonth();
        $currency = (string) config('services.stripe.currency', 'USD');

        return [
            'public_id' => $batch->public_id,
            'batch_month' => $batch->batch_month?->toDateString(),
            'period_start' => $monthStart?->toDateString(),
            'period_end' => $monthEnd?->toDateString(),
            'total_pool' => number_format((float) $batch->total_pool, 2, '.', ''),
            'total_distributed' => number_format((float) $batch->total_distributed, 2, '.', ''),
            'total_amount' => number_format((float) $batch->total_distributed, 2, '.', ''),
            'organization_count' => (int) $batch->total_organizations,
            'total_organizations' => (int) $batch->total_organizations,
            'total_items' => $batch->relationLoaded('items') ? $batch->items->count() : (int) $batch->total_organizations,
            'currency' => $currency,
            'status' => $batch->status,
            'created_at' => $batch->created_at?->toIso8601String(),
            'processed_at' => $batch->processed_at?->toIso8601String(),
            'executed_at' => $batch->processed_at?->toIso8601String(),
            'metadata' => $batch->metadata,
        ];
    }
}
