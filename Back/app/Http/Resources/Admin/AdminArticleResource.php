<?php

namespace App\Http\Resources\Admin;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'organization' => [
                'public_id' => $article->organizationProfile?->public_id,
                'name' => $article->organizationProfile?->organization_name,
            ],
            'author' => [
                'public_id' => $article->organizationProfile?->user?->public_id,
                'name' => $article->organizationProfile?->user?->full_name,
                'email' => $article->organizationProfile?->user?->email,
            ],
            'category' => $article->category,
            'status' => $article->trashed() ? 'archived' : $article->status,
            'submitted_at' => $article->created_at?->toIso8601String(),
            'published_at' => $article->published_at?->toIso8601String(),
            'total_reads' => (int) $article->total_reads,
            'featured' => $article->featured_at !== null,
            'is_featured' => $article->featured_at !== null,
            'featured_at' => $article->featured_at?->toIso8601String(),
            'read_time_minutes' => $article->estimatedReadTimeMinutes(),
            'total_unique_reads' => (int) ($article->total_unique_reads ?? 0),
            'total_points_generated' => (int) ($article->total_points_generated ?? 0),
            'archived_at' => $article->deleted_at?->toIso8601String(),
        ];
    }
}
