<?php

namespace App\Services\PublicApi;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\OrganizationProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicOrganizationService
{
    public function paginate(Request $request): array
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 50);
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));

        $query = OrganizationProfile::query()
            ->where('verification_status', 'approved')
            ->withCount(['articles as published_articles_count' => function ($q): void {
                $q->where('status', 'published');
            }])
            ->withSum(['articles as total_reads' => function ($q): void {
                $q->where('status', 'published');
            }], 'total_reads');

        if ($search !== '') {
            $query->where('organization_name', 'like', '%'.$search.'%');
        }

        if ($category !== '') {
            $query->whereHas('articles', function ($q) use ($category): void {
                $q->where('status', 'published')->where('category', $category);
            });
        }

        $query->orderByDesc('published_articles_count')->orderByDesc('created_at');

        $paginator = $query->paginate($perPage)->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (OrganizationProfile $org): array => $this->summary($org))
            ->values()
            ->all();

        return [
            'organizations' => [
                'data' => $items,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }

    public function show(string $publicId): array
    {
        $organization = OrganizationProfile::query()
            ->where('public_id', $publicId)
            ->where('verification_status', 'approved')
            ->withCount(['articles as published_articles_count' => function ($q): void {
                $q->where('status', 'published');
            }])
            ->withSum(['articles as total_reads' => function ($q): void {
                $q->where('status', 'published');
            }], 'total_reads')
            ->first();

        if ($organization === null) {
            throw new ApiException('The requested organization was not found.', 404);
        }

        $articles = Article::query()
            ->where('organization_profile_id', $organization->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        return [
            'organization' => $this->detail($organization),
            'articles' => $articles->map(fn (Article $article): array => $this->articleSummary($article))->values()->all(),
        ];
    }

    protected function summary(OrganizationProfile $org): array
    {
        $metadata = is_array($org->metadata) ? $org->metadata : [];
        $logoPath = $metadata['logo_path'] ?? null;

        return [
            'public_id' => $org->public_id,
            'name' => $org->organization_name,
            'verification_status' => $org->verification_status,
            'website' => $org->website,
            'logo_url' => $logoPath ? Storage::url($logoPath) : null,
            'published_articles_count' => (int) ($org->published_articles_count ?? 0),
            'total_reads' => (int) ($org->total_reads ?? 0),
            'description' => $metadata['mission_statement'] ?? null,
        ];
    }

    protected function detail(OrganizationProfile $org): array
    {
        $metadata = is_array($org->metadata) ? $org->metadata : [];
        $logoPath = $metadata['logo_path'] ?? null;

        return [
            'public_id' => $org->public_id,
            'name' => $org->organization_name,
            'verification_status' => $org->verification_status,
            'website' => $org->website,
            'description' => $metadata['mission_statement'] ?? null,
            'logo_url' => $logoPath ? Storage::url($logoPath) : null,
            'published_articles_count' => (int) ($org->published_articles_count ?? 0),
            'total_reads' => (int) ($org->total_reads ?? 0),
            'joined_at' => $org->created_at?->toIso8601String(),
        ];
    }

    protected function articleSummary(Article $article): array
    {
        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'published_at' => $article->published_at?->toIso8601String(),
            'read_time_minutes' => $article->read_time,
            'total_reads' => (int) $article->total_reads,
        ];
    }
}
