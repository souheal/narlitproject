<?php

namespace App\Http\Resources\Admin;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;
        $metadata = $article->metadata ?? [];

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'category' => $article->category,
            'status' => $article->trashed() ? 'archived' : $article->status,
            'rejection_reason' => $article->rejection_reason,
            'organization' => [
                'public_id' => $article->organizationProfile?->public_id,
                'name' => $article->organizationProfile?->organization_name,
                'website' => $article->organizationProfile?->website,
                'verification_status' => $article->organizationProfile?->verification_status,
                'author' => [
                    'public_id' => $article->organizationProfile?->user?->public_id,
                    'name' => $article->organizationProfile?->user?->full_name,
                    'email' => $article->organizationProfile?->user?->email,
                ],
            ],
            'images' => $metadata['images'] ?? [],
            'metadata' => $metadata,
            'read_statistics' => [
                'total_reads' => (int) $article->total_reads,
                'total_unique_reads' => (int) $article->total_unique_reads,
                'total_reading_seconds' => (int) $article->total_reading_seconds,
                'total_points_generated' => (int) $article->total_points_generated,
                'recorded_read_events' => (int) $article->getAttribute('recorded_read_events'),
            ],
            'dates' => [
                'submitted_at' => $article->created_at?->toIso8601String(),
                'updated_at' => $article->updated_at?->toIso8601String(),
                'published_at' => $article->published_at?->toIso8601String(),
                'featured_at' => $article->featured_at?->toIso8601String(),
                'archived_at' => $article->deleted_at?->toIso8601String(),
            ],
            'submission_history' => $article->getAttribute('submission_history')
                ->map(fn (object $log): array => $this->historyPayload($log))
                ->all(),
            'moderation_history' => $article->getAttribute('moderation_history')
                ->map(fn (object $log): array => $this->historyPayload($log))
                ->all(),
        ];
    }

    protected function historyPayload(object $log): array
    {
        $metadata = is_string($log->metadata ?? null)
            ? json_decode($log->metadata, true)
            : ($log->metadata ?? null);

        return [
            'action' => $log->action,
            'admin_name' => $log->admin_name,
            'reason' => is_array($metadata) ? ($metadata['reason'] ?? null) : null,
            'from_status' => is_array($metadata) ? ($metadata['from_status'] ?? null) : null,
            'to_status' => is_array($metadata) ? ($metadata['to_status'] ?? null) : null,
            'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
        ];
    }
}
