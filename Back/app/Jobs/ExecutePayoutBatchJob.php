<?php

namespace App\Jobs;

use App\Services\Admin\AdminPayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecutePayoutBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $payoutBatchId,
    ) {}

    public function handle(AdminPayoutService $payouts): void
    {
        $payouts->processBatch($this->payoutBatchId);
    }
}
