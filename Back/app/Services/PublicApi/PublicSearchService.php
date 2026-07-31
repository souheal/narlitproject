<?php

namespace App\Services\PublicApi;

use App\Models\Article;
use App\Models\OrganizationProfile;
use Illuminate\Http\Request;

class PublicSearchService
{
    public function search(Request $request): array
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $limit = min(max((int) $request->query('limit', 10), 1), 20);

        $articles = [];
        $organizations = [];

        if ($query === '') {
            return [
                'results' => [
                    'articles' => $articles,
                    'organizations' => $organizations,
                    'total' => 0,
                ],
            ];
        }

        $like = '%'.$query.'%';

        if ($type === 'all' || $type === 'articles') {
            $articles = Article::query()
                ->with('organizationProfile')
                ->where('status', 'published')
                ->where(function ($q) use ($like): void {
                    $q->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like);
                })
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
                ->map(fn (Article $article): array => $this->articleSummary($article))
                ->values()
                ->all();
        }

        if ($type === 'all' || $type === 'organizations') {
            $organizations = OrganizationProfile::query()
                ->where('verification_status', 'approved')
                ->where('organization_name', 'like', $like)
                ->withCount(['articles as published_articles_count' => function ($q): void {
                    $q->where('status', 'published');
                }])
                ->orderByDesc('published_articles_count')
                ->orderBy('organization_name')
                ->limit($limit)
                ->get()
                ->map(fn (OrganizationProfile $org): array => $this->organizationSummary($org))
                ->values()
                ->all();
        }

        return [
            'results' => [
                'articles' => $articles,
                'organizations' => $organizations,
                'total' => count($articles) + count($organizations),
            ],
        ];
    }

    protected function articleSummary(Article $article): array
    {
        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'organization' => [
                'name' => $article->organizationProfile?->organization_name,
                'public_id' => $article->organizationProfile?->public_id,
            ],
            'published_at' => $article->published_at?->toIso8601String(),
            'read_time_minutes' => $article->read_time,
        ];
    }

    protected function organizationSummary(OrganizationProfile $org): array
    {
        $metadata = is_array($org->metadata) ? $org->metadata : [];

        return [
            'public_id' => $org->public_id,
            'name' => $org->organization_name,
            'verification_status' => $org->verification_status,
            'description' => $metadata['mission_statement'] ?? null,
            'published_articles_count' => (int) ($org->published_articles_count ?? 0),
        ];
    }
}
