<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredSanctumTokens extends Command
{
    protected $signature = 'sanctum:cleanup-expired-tokens';

    protected $description = 'Delete expired Sanctum personal access tokens.';

    public function handle(): int
    {
        $deleted = DB::table('personal_access_tokens')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired Sanctum tokens.");

        return self::SUCCESS;
    }
}
