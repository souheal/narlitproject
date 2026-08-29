<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminSecurityAuditService
{
    public function __construct(
        protected AdminAuditLogService $auditLogService,
    ) {}

    public function log(?User $admin, string $action, Request $request, array $metadata = [], string $entityType = 'user', ?string $entityId = null): void
    {
        if ($admin === null || ! Schema::hasTable('admin_logs')) {
            return;
        }

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId ?? $admin->public_id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($this->auditLogService->redact($metadata)),
            'created_at' => now(),
        ]);
    }
}
