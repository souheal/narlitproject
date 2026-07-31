<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAuditLogIndexRequest;
use App\Http\Resources\Admin\AdminAuditLogResource;
use App\Services\Admin\AdminAuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    use ApiResponse;

    public function index(AdminAuditLogIndexRequest $request, AdminAuditLogService $audit): JsonResponse
    {
        $logs = $audit->paginate($request);
        $audit->logView($request, 'audit_log.viewed', null, ['filters' => $request->query()]);
        $payload = AdminAuditLogResource::collection($logs)->response()->getData(true);

        return $this->success('Audit logs retrieved successfully.', [
            'audit_logs' => $payload,
            'entries' => $payload,
        ]);
    }

    public function show(int $id, Request $request, AdminAuditLogService $audit): JsonResponse
    {
        $log = $audit->find($id);
        $audit->logView($request, 'audit_log.detail_viewed', (string) $id);

        return $this->success('Audit log retrieved successfully.', [
            'audit_log' => new AdminAuditLogResource($log),
        ]);
    }

    public function export(AdminAuditLogIndexRequest $request, AdminAuditLogService $audit): StreamedResponse
    {
        $audit->logView($request, 'audit_log.exported', null, ['filters' => $request->query()]);

        return $audit->export($request);
    }
}
