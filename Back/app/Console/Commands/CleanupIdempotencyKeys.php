<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:cleanup';

    protected $description = 'Delete expired completed and failed idempotency records.';

    public function handle(): int
    {
        $deleted = DB::table('idempotency_keys')
            ->whereIn('status', ['completed', 'failed'])
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency records.");

        return self::SUCCESS;
    }
}
