<?php

namespace App\Http\Resources\Admin;

use App\Services\Admin\AdminAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $audit = app(AdminAuditLogService::class);

        return [
            'id' => $this->resource->id,
            'timestamp' => $this->resource->created_at,
            'actor' => [
                'public_id' => $this->resource->admin_public_id,
                'name' => $this->resource->admin_name,
                'email' => $this->resource->admin_email,
            ],
            'action' => $this->resource->action,
            'entity_type' => $this->resource->entity_type,
            'entity_id' => $this->resource->entity_id,
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'device_summary' => $this->deviceSummary((string) $this->resource->user_agent),
            'status' => $audit->status($this->resource),
            'metadata' => $audit->redact($audit->decodeMetadata($this->resource->metadata)),
            'target_admin_path' => $this->targetPath(),
        ];
    }

    protected function deviceSummary(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $browser = str_contains($userAgent, 'Chrome') ? 'Chrome' : (str_contains($userAgent, 'Firefox') ? 'Firefox' : 'Browser');
        $platform = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Mac') ? 'macOS' : 'Device');

        return "{$browser} on {$platform}";
    }

    protected function targetPath(): ?string
    {
        if ($this->resource->entity_id === null) {
            return null;
        }

        return match ($this->resource->entity_type) {
            'user' => "/admin/users/{$this->resource->entity_id}",
            'article' => "/admin/articles/{$this->resource->entity_id}",
            'organization', 'organization_profile' => "/admin/organizations/{$this->resource->entity_id}",
            'subscription' => "/admin/subscriptions/{$this->resource->entity_id}",
            'payout_batch' => "/admin/payouts/{$this->resource->entity_id}",
            default => null,
        };
    }
}
