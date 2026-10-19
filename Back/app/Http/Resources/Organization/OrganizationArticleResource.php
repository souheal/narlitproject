<?php

namespace App\Http\Resources\Organization;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'status' => $article->trashed() ? 'archived' : $article->status,
            'read_time_minutes' => $article->read_time,
            'total_reads' => (int) $article->total_reads,
            'total_unique_reads' => (int) $article->total_unique_reads,
            'total_points_generated' => (int) $article->total_points_generated,
            'review_feedback' => $article->rejection_reason,
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
            'published_at' => $article->published_at?->toIso8601String(),
            'featured_at' => $article->featured_at?->toIso8601String(),
            'archived_at' => $article->deleted_at?->toIso8601String(),
        ];
    }
}
