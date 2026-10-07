<?php

namespace App\Services\Public;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicArticleService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
    ) {
    }

    public function paginate(Request $request): array
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));
        $category = $this->normalizeCategory($request->query('category'));

        $articles = Article::query()
            ->select([
                'id',
                'public_id',
                'organization_profile_id',
                'title',
                'slug',
                'excerpt',
                'category',
                'published_at',
                'featured_at',
                'read_time',
                'total_reads',
                'total_unique_reads',
            ])
            ->with(['organizationProfile:id,public_id,organization_name,metadata'])
            ->where('status', 'published')
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->orderByDesc('featured_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'articles' => $articles->through(fn (Article $article): array => $this->cardPayload($article)),
            'categories' => $this->availableCategories(),
        ];
    }

    public function detail(string $identifier, ?User $user): array
    {
        $article = Article::query()
            ->select([
                'id',
                'public_id',
                'organization_profile_id',
                'title',
                'slug',
                'excerpt',
                'content',
                'category',
                'published_at',
                'featured_at',
                'read_time',
                'total_reads',
                'total_unique_reads',
            ])
            ->with(['organizationProfile:id,public_id,organization_name,metadata'])
            ->where('status', 'published')
            ->where(function ($query) use ($identifier): void {
                $query->where('public_id', $identifier)
                    ->orWhere('slug', $identifier);
            })
            ->first();

        if ($article === null) {
            throw new ApiException('The requested article was not found.', 404);
        }

        $canReadFull = $this->canReadFull($user);

        return [
            'article' => $this->detailPayload($article, $canReadFull),
            'access' => [
                'can_read_full' => $canReadFull,
                'requires_subscription' => ! $canReadFull,
            ],
        ];
    }

    public function canReadFull(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->email_verified_at === null || ! $user->is_active) {
            return false;
        }

        if ($user->isAdminAccount() || $this->roleName($user) === 'organization') {
            return false;
        }

        return $this->subscriptions->userHasActiveSubscription($user);
    }

    protected function cardPayload(Article $article): array
    {
        return [
            'public_id' => $article->public_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'organization' => $this->organizationPayload($article),
            'read_time_minutes' => $article->read_time,
            'published_at' => $article->published_at?->toIso8601String(),
            'total_reads' => (int) $article->total_reads,
            'total_unique_reads' => (int) $article->total_unique_reads,
        ];
    }

    protected function detailPayload(Article $article, bool $canReadFull): array
    {
        $payload = $this->cardPayload($article);
        $payload['preview'] = [
            'excerpt' => $article->excerpt,
            'has_dedicated_preview' => $article->excerpt !== null && trim($article->excerpt) !== '',
        ];

        if ($canReadFull) {
            $payload['content'] = $article->content;
        }

        return $payload;
    }

    protected function organizationPayload(Article $article): array
    {
        $organization = $article->organizationProfile;
        $metadata = is_array($organization?->metadata) ? $organization->metadata : [];

        return [
            'public_id' => $organization?->public_id,
            'name' => $organization?->organization_name,
            'logo_url' => $metadata['logo_url'] ?? null,
        ];
    }

    protected function availableCategories(): array
    {
        return Article::query()
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('category')
            ->distinct()
            ->pluck('category')
            ->map(fn (string $category): string => trim($category))
            ->filter(fn (string $category): bool => $category !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeCategory(mixed $category): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $category = trim($category);

        return $category === '' ? null : $category;
    }

    protected function roleName(User $user): ?string
    {
        return DB::table('roles')->where('id', $user->role_id)->value('name');
    }
}
