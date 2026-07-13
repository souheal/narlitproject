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

        return [
            'public_id' => $batch->public_id,
            'batch_month' => $batch->batch_month?->toDateString(),
            'total_pool' => number_format((float) $batch->total_pool, 2, '.', ''),
            'total_distributed' => number_format((float) $batch->total_distributed, 2, '.', ''),
            'organization_count' => (int) $batch->total_organizations,
            'status' => $batch->status,
            'created_at' => $batch->created_at?->toIso8601String(),
            'processed_at' => $batch->processed_at?->toIso8601String(),
            'metadata' => $batch->metadata,
        ];
    }
}
