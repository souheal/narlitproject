<?php

namespace App\Services\Public;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\ImpactTransaction;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicOrganizationService
{
    public function paginate(Request $request, ?User $user): array
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $category = $this->normalizeCategory($request->query('category'));

        $organizations = OrganizationProfile::query()
            ->where('verification_status', 'approved')
            ->when($category !== null, function ($query) use ($category): void {
                $query->whereExists(function ($subquery) use ($category): void {
                    $subquery->selectRaw('1')
                        ->from('articles')
                        ->whereColumn('articles.organization_profile_id', 'organization_profiles.id')
                        ->whereNull('articles.deleted_at')
                        ->where('articles.status', 'published')
                        ->where('articles.category', $category);
                });
            })
            ->orderBy('organization_name')
            ->paginate($perPage);

        $this->hydrateComputedFields($organizations->getCollection(), $user);

        return [
            'organizations' => $organizations->through(fn (OrganizationProfile $organization): array => $this->payload($organization)),
            'categories' => $this->availableCategories(),
        ];
    }

    public function find(string $publicId, ?User $user): array
    {
        $organization = OrganizationProfile::query()
            ->where('public_id', $publicId)
            ->where('verification_status', 'approved')
            ->first();

        if ($organization === null) {
            throw new ApiException('The requested organization was not found.', 404);
        }

        $this->hydrateComputedFields(collect([$organization]), $user);

        return $this->payload($organization);
    }

    protected function hydrateComputedFields(Collection $organizations, ?User $user): void
    {
        if ($organizations->isEmpty()) {
            return;
        }

        $organizationIds = $organizations->pluck('id')->all();

        $articleStats = Article::query()
            ->whereIn('organization_profile_id', $organizationIds)
            ->where('status', 'published')
            ->groupBy('organization_profile_id')
            ->selectRaw('organization_profile_id, COUNT(*) as published_articles_count, COALESCE(SUM(total_reads), 0) as total_reads')
            ->get()
            ->keyBy('organization_profile_id');

        $categoryRows = Article::query()
            ->whereIn('organization_profile_id', $organizationIds)
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('category')
            ->get(['organization_profile_id', 'category'])
            ->groupBy('organization_profile_id')
            ->map(fn (Collection $rows): array => $rows
                ->pluck('category')
                ->map(fn (string $category): string => trim($category))
                ->filter(fn (string $category): bool => $category !== '')
                ->unique()
                ->values()
                ->all());

        $supporterCounts = ImpactTransaction::query()
            ->whereIn('organization_profile_id', $organizationIds)
            ->groupBy('organization_profile_id')
            ->selectRaw('organization_profile_id, COUNT(DISTINCT user_id) as supporters_count')
            ->pluck('supporters_count', 'organization_profile_id');

        $supportedOrganizationIds = $user === null
            ? collect()
            : ImpactTransaction::query()
                ->where('user_id', $user->id)
                ->whereIn('organization_profile_id', $organizationIds)
                ->distinct()
                ->pluck('organization_profile_id')
                ->flip();

        $organizations->each(function (OrganizationProfile $organization) use ($articleStats, $categoryRows, $supporterCounts, $supportedOrganizationIds): void {
            $stats = $articleStats->get($organization->id);

            $organization->setAttribute('public_categories', $categoryRows->get($organization->id, []));
            $organization->setAttribute('published_articles_count', (int) ($stats?->published_articles_count ?? 0));
            $organization->setAttribute('public_total_reads', (int) ($stats?->total_reads ?? 0));
            $organization->setAttribute('supporters_count', (int) ($supporterCounts[$organization->id] ?? 0));
            $organization->setAttribute('is_supported_by_me', $supportedOrganizationIds->has($organization->id));
        });
    }

    protected function availableCategories(): array
    {
        return Article::query()
            ->join('organization_profiles', 'organization_profiles.id', '=', 'articles.organization_profile_id')
            ->where('organization_profiles.verification_status', 'approved')
            ->whereNull('organization_profiles.deleted_at')
            ->where('articles.status', 'published')
            ->whereNotNull('articles.category')
            ->where('articles.category', '<>', '')
            ->orderBy('articles.category')
            ->distinct()
            ->pluck('articles.category')
            ->map(fn (string $category): string => trim($category))
            ->filter(fn (string $category): bool => $category !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function payload(OrganizationProfile $organization): array
    {
        $metadata = is_array($organization->metadata) ? $organization->metadata : [];

        return [
            'public_id' => $organization->public_id,
            'name' => $organization->organization_name,
            'verification_status' => $organization->verification_status,
            'website' => $organization->website,
            'logo_url' => $metadata['logo_url'] ?? null,
            'published_articles_count' => (int) $organization->getAttribute('published_articles_count'),
            'total_reads' => (int) $organization->getAttribute('public_total_reads'),
            'description' => $metadata['description'] ?? null,
            'categories' => $organization->getAttribute('public_categories') ?? [],
            'supporters_count' => (int) $organization->getAttribute('supporters_count'),
            'is_supported_by_me' => (bool) $organization->getAttribute('is_supported_by_me'),
        ];
    }

    protected function normalizeCategory(mixed $category): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $category = trim($category);

        return $category === '' ? null : $category;
    }
}
