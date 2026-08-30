<?php

namespace App\Jobs;

use App\Models\PayoutBatch;
use App\Services\Admin\AdminPayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecutePayoutBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public function __construct(
        public int $payoutBatchId,
    ) {}

    public function handle(AdminPayoutService $payouts): void
    {
        $payouts->processBatch($this->payoutBatchId);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        $batch = PayoutBatch::query()->find($this->payoutBatchId);

        if ($batch === null || $batch->status === 'completed' || $batch->status === 'canceled') {
            return;
        }

        $batch->forceFill([
            'status' => 'failed',
            'processed_at' => now(),
            'metadata' => array_merge($batch->metadata ?? [], [
                'queue_failure' => [
                    'failed_at' => now()->toIso8601String(),
                    'exception' => $exception::class,
                ],
            ]),
        ])->save();
    }
}
