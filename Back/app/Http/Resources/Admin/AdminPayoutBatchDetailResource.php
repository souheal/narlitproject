<?php

namespace App\Http\Resources\Admin;

use App\Models\PayoutBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminPayoutBatchDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PayoutBatch $batch */
        $batch = $this->resource;

        return [
            'batch' => new AdminPayoutBatchResource($batch),
            'items' => AdminPayoutItemResource::collection($batch->items)->resolve($request),
            'admin_action_history' => collect($batch->getAttribute('admin_action_history') ?? [])
                ->map(fn (object $log): array => [
                    'action' => $log->action,
                    'admin_name' => $log->admin_name,
                    'metadata' => is_string($log->metadata ?? null) ? json_decode($log->metadata, true) : null,
                    'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
