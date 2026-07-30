<?php

namespace App\Services\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogService
{
    public const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'otp',
        'otp_code',
        'mfa',
        'mfa_code',
        'phone_mfa_code',
        'token',
        'access_token',
        'authorization',
        'secret',
        'stripe_secret',
        'api_key',
        'client_secret',
    ];

    public function paginate(Request $request): LengthAwarePaginator
    {
        return $this->filteredQuery($request)
            ->latest('admin_logs.created_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));
    }

    public function find(int $id): object
    {
        $log = $this->baseQuery()
            ->where('admin_logs.id', $id)
            ->first();

        if ($log === null) {
            abort(404, 'Audit log was not found.');
        }

        return $log;
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'admin-audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'timestamp',
                'actor_name',
                'actor_email',
                'action',
                'entity_type',
                'entity_id',
                'ip_address',
                'user_agent',
                'status',
                'metadata',
            ]);

            $this->filteredQuery($request)
                ->latest('admin_logs.created_at')
                ->chunk(200, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $this->csvValue($log->created_at),
                            $this->csvValue($log->admin_name),
                            $this->csvValue($log->admin_email),
                            $this->csvValue($log->action),
                            $this->csvValue($log->entity_type),
                            $this->csvValue($log->entity_id),
                            $this->csvValue($log->ip_address),
                            $this->csvValue($log->user_agent),
                            $this->csvValue($this->status($log)),
                            $this->csvValue(json_encode($this->redact($this->decodeMetadata($log->metadata)))),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function logView(Request $request, string $action, ?string $entityId = null, array $metadata = []): void
    {
        $admin = $request->user();

        if ($admin === null) {
            return;
        }

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => 'audit_log',
            'entity_id' => $entityId,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($this->redact($metadata)),
            'created_at' => now(),
        ]);
    }

    public function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = $this->redact($item);
        }

        return $redacted;
    }

    public function decodeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            return $metadata;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function status(object $log): string
    {
        $metadata = $this->decodeMetadata($log->metadata);

        if (isset($metadata['status']) && is_string($metadata['status'])) {
            return $metadata['status'];
        }

        if (str_contains((string) $log->action, 'failed')) {
            return 'failure';
        }

        return 'success';
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = $this->baseQuery();
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('admin_logs.action', 'like', "%{$search}%")
                    ->orWhere('admin_logs.entity_type', 'like', "%{$search}%")
                    ->orWhere('admin_logs.entity_id', 'like', "%{$search}%")
                    ->orWhere('users.full_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('actor')) {
            $query->where('users.public_id', $request->query('actor'));
        }

        foreach (['action', 'entity_type', 'ip_address'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("admin_logs.{$filter}", $request->query($filter));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('admin_logs.created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('admin_logs.created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            $status === 'failure'
                ? $query->where('admin_logs.action', 'like', '%failed%')
                : $query->where('admin_logs.action', 'not like', '%failed%');
        }

        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy('admin_logs.created_at', $direction)->orderBy('admin_logs.id', $direction);

        return $query;
    }

    protected function baseQuery(): Builder
    {
        return DB::table('admin_logs')
            ->join('users', 'users.id', '=', 'admin_logs.admin_id')
            ->select([
                'admin_logs.id',
                'admin_logs.admin_id',
                'admin_logs.action',
                'admin_logs.entity_type',
                'admin_logs.entity_id',
                'admin_logs.ip_address',
                'admin_logs.user_agent',
                'admin_logs.metadata',
                'admin_logs.created_at',
                'users.public_id as admin_public_id',
                'users.full_name as admin_name',
                'users.email as admin_email',
            ]);
    }

    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    protected function csvValue(mixed $value): string
    {
        $string = (string) $value;

        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@'], true)) {
            return "'".$string;
        }

        return $string;
    }
}
