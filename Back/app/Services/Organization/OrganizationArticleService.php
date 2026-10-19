<?php

namespace App\Services\Organization;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationArticleService
{
    public function paginate(OrganizationProfile $organization, Request $request): LengthAwarePaginator
    {
        $query = $organization->articles()
            ->select([
                'id',
                'public_id',
                'organization_profile_id',
                'title',
                'slug',
                'excerpt',
                'category',
                'status',
                'rejection_reason',
                'featured_at',
                'published_at',
                'read_time',
                'total_reads',
                'total_unique_reads',
                'total_points_generated',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

        if ($request->query('status') === 'archived') {
            $query->onlyTrashed();
        } else {
            $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')));
        }

        $query->when($request->filled('category'), fn ($query) => $query->where('category', $request->query('category')));

        return $query
            ->latest('updated_at')
            ->latest('id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));
    }

    public function create(OrganizationProfile $organization, array $data): Article
    {
        return DB::transaction(function () use ($organization, $data): Article {
            $article = $organization->articles()->create([
                'public_id' => (string) Str::uuid(),
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'category' => $data['category'] ?? null,
                'status' => 'draft',
                'read_time' => $this->readTime($data['content']),
                'total_reads' => 0,
                'total_unique_reads' => 0,
                'total_reading_seconds' => 0,
                'total_points_generated' => 0,
            ]);

            return $article->refresh();
        }, 3);
    }

    public function details(OrganizationProfile $organization, string $publicId): Article
    {
        return $this->findOwnedArticle($organization, $publicId);
    }

    public function update(OrganizationProfile $organization, string $publicId, array $data): Article
    {
        return DB::transaction(function () use ($organization, $publicId, $data): Article {
            $article = $this->findOwnedArticle($organization, $publicId, true);

            if ($article->status !== 'draft') {
                throw new ApiException('Only draft articles can be edited.', 422);
            }

            $attributes = [];

            foreach (['title', 'excerpt', 'content', 'category'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (array_key_exists('slug', $data) && $data['slug'] !== null) {
                $attributes['slug'] = $this->uniqueSlug($data['slug'], $article->id);
            }

            if (array_key_exists('content', $attributes)) {
                $attributes['read_time'] = $this->readTime($attributes['content']);
            }

            if ($attributes !== []) {
                $article->forceFill($attributes)->save();
            }

            return $article->refresh();
        }, 3);
    }

    public function submit(OrganizationProfile $organization, User $user, string $publicId, Request $request): Article
    {
        return DB::transaction(function () use ($organization, $user, $publicId, $request): Article {
            $article = $this->findOwnedArticle($organization, $publicId, true);

            if ($article->status !== 'draft') {
                throw new ApiException('Only draft articles can be submitted for review.', 422);
            }

            $this->ensureCompleteForReview($article);

            $fromStatus = $article->status;
            $article->forceFill([
                'status' => 'pending_review',
                'rejection_reason' => null,
                'featured_at' => null,
                'published_at' => null,
            ])->save();

            $this->logSubmission($article, $user, $request, $fromStatus);

            return $article->refresh();
        }, 3);
    }

    protected function findOwnedArticle(OrganizationProfile $organization, string $publicId, bool $lock = false): Article
    {
        $article = $organization->articles()
            ->where('public_id', $publicId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        return $article;
    }

    protected function ensureCompleteForReview(Article $article): void
    {
        if (trim((string) $article->title) === '') {
            throw new ApiException('Please add a title before submitting this article.', 422);
        }

        if (trim(strip_tags((string) $article->content)) === '') {
            throw new ApiException('Please add article content before submitting this article.', 422);
        }
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = 'article';
        }

        $base = Str::limit($base, 240, '');
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $tail = '-'.$suffix++;
            $slug = Str::limit($base, 255 - strlen($tail), '').$tail;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId): bool
    {
        return Article::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    protected function readTime(string $content): int
    {
        $wordCount = Str::wordCount(trim(strip_tags($content)));

        return $wordCount === 0 ? 0 : max(1, (int) ceil($wordCount / 200));
    }

    protected function logSubmission(Article $article, User $user, Request $request, string $fromStatus): void
    {
        if (! Schema::hasTable('admin_logs')) {
            return;
        }

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'article.submitted',
            'entity_type' => 'article',
            'entity_id' => $article->public_id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => json_encode([
                'from_status' => $fromStatus,
                'to_status' => 'pending_review',
                'submitted_by' => $user->public_id,
            ]),
            'created_at' => now(),
        ]);
    }
}
