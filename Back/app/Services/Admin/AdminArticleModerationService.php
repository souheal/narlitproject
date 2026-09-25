<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Article;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminArticleModerationService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Article::query()
            ->with(['organizationProfile.user']);

        if ($request->query('status') === 'archived') {
            $query->onlyTrashed();
        } else {
            $query->where('status', $request->query('status', 'pending_review'));
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(min(max((int) $request->query('per_page', 15), 1), 100));
    }

    public function details(string $publicId): Article
    {
        $article = Article::withTrashed()
            ->with(['organizationProfile.user'])
            ->where('public_id', $publicId)
            ->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        $article->setAttribute('recorded_read_events', $article->reads()->count());
        $article->setAttribute('submission_history', $this->history($article, ['article.submitted']));
        $article->setAttribute('moderation_history', $this->history($article, [
            'article.approved',
            'article.rejected',
            'article.changes_requested',
            'article.published',
            'article.featured',
            'article.unfeatured',
            'article.archived',
            'article.restored',
        ]));

        return $article;
    }

    public function approve(User $admin, string $publicId, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);
        $this->ensureStatus($article, ['pending_review'], 'Only pending articles can be approved.');

        return $this->publishArticle($admin, $article, 'article.approved', $request);
    }

    public function reject(User $admin, string $publicId, string $reason, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);
        $this->ensureStatus($article, ['pending_review', 'draft'], 'Only draft or pending articles can be rejected.');

        return DB::transaction(function () use ($admin, $article, $reason, $request): Article {
            $fromStatus = $article->status;
            $article->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'featured_at' => null,
            ])->save();

            $this->log($admin, $article, 'article.rejected', $request, [
                'reason' => $reason,
                'from_status' => $fromStatus,
                'to_status' => 'rejected',
            ]);

            return $article->refresh();
        }, 3);
    }

    public function requestChanges(User $admin, string $publicId, string $reason, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);
        $this->ensureStatus($article, ['pending_review'], 'Only pending articles can be returned for changes.');

        return DB::transaction(function () use ($admin, $article, $reason, $request): Article {
            $fromStatus = $article->status;
            $metadata = $article->metadata ?? [];
            $metadata['last_change_request'] = [
                'reason' => $reason,
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $admin->public_id,
            ];

            $article->forceFill([
                'status' => 'draft',
                'rejection_reason' => $reason,
                'metadata' => $metadata,
                'featured_at' => null,
            ])->save();

            $this->log($admin, $article, 'article.changes_requested', $request, [
                'reason' => $reason,
                'from_status' => $fromStatus,
                'to_status' => 'draft',
            ]);

            return $article->refresh();
        }, 3);
    }

    public function publish(User $admin, string $publicId, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);
        $this->ensureStatus($article, ['draft', 'pending_review', 'rejected'], 'This article cannot be published from its current status.');

        return $this->publishArticle($admin, $article, 'article.published', $request);
    }

    public function feature(User $admin, string $publicId, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);
        $this->ensureStatus($article, ['published'], 'Only published articles can be featured.');

        return DB::transaction(function () use ($admin, $article, $request): Article {
            $article->forceFill([
                'featured_at' => $article->featured_at ?? now(),
            ])->save();

            $this->log($admin, $article, 'article.featured', $request);

            return $article->refresh();
        }, 3);
    }

    public function unfeature(User $admin, string $publicId, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);

        return DB::transaction(function () use ($admin, $article, $request): Article {
            $article->forceFill([
                'featured_at' => null,
            ])->save();

            $this->log($admin, $article, 'article.unfeatured', $request);

            return $article->refresh();
        }, 3);
    }

    public function archive(User $admin, string $publicId, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);

        return DB::transaction(function () use ($admin, $article, $request): Article {
            $this->log($admin, $article, 'article.archived', $request, [
                'from_status' => $article->status,
                'to_status' => 'archived',
            ]);
            $article->delete();

            return $article->refresh();
        }, 3);
    }

    public function restore(User $admin, string $publicId, Request $request): Article
    {
        $article = Article::withTrashed()->where('public_id', $publicId)->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        if (! $article->trashed()) {
            throw new ApiException('Only archived articles can be restored.', 422);
        }

        return DB::transaction(function () use ($admin, $article, $request): Article {
            $article->restore();
            $this->log($admin, $article, 'article.restored', $request, [
                'from_status' => 'archived',
                'to_status' => $article->status,
            ]);

            return $article->refresh();
        }, 3);
    }

    public function update(User $admin, string $publicId, array $data, Request $request): Article
    {
        $article = $this->findActiveArticle($publicId);

        return DB::transaction(function () use ($admin, $article, $data, $request): Article {
            $changed = [];
            $update = [];

            foreach (['title', 'excerpt', 'content', 'category'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $newValue = $data[$field];
                $oldValue = $article->{$field};

                if ($newValue === $oldValue) {
                    continue;
                }

                $update[$field] = $newValue;
                $changed[$field] = [
                    'from' => is_string($oldValue) && strlen($oldValue) > 200
                        ? substr($oldValue, 0, 200).'…'
                        : $oldValue,
                    'to' => is_string($newValue) && strlen($newValue) > 200
                        ? substr($newValue, 0, 200).'…'
                        : $newValue,
                ];
            }

            if ($update !== []) {
                $article->forceFill($update)->save();
            }

            $this->log($admin, $article, 'article.edited', $request, [
                'changed_fields' => array_keys($changed),
                'changes' => $changed,
                'edit_note' => $data['edit_note'] ?? null,
            ]);

            return $article->refresh();
        }, 3);
    }

    public function destroy(User $admin, string $publicId, string $reason, Request $request): void
    {
        $article = Article::withTrashed()->where('public_id', $publicId)->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        DB::transaction(function () use ($admin, $article, $reason, $request): void {
            $this->log($admin, $article, 'article.deleted', $request, [
                'reason' => $reason,
                'title' => $article->title,
                'status_before_delete' => $article->status,
                'was_archived' => $article->trashed(),
            ]);

            $article->forceDelete();
        }, 3);
    }

    protected function publishArticle(User $admin, Article $article, string $action, Request $request): Article
    {
        return DB::transaction(function () use ($admin, $article, $action, $request): Article {
            $fromStatus = $article->status;
            $article->forceFill([
                'status' => 'published',
                'published_at' => $article->published_at ?? now(),
                'rejection_reason' => null,
            ])->save();

            $this->log($admin, $article, $action, $request, [
                'from_status' => $fromStatus,
                'to_status' => 'published',
            ]);

            return $article->refresh();
        }, 3);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhereHas('organizationProfile', fn (Builder $organization): Builder => $organization
                        ->where('organization_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('organization')) {
            $query->whereHas('organizationProfile', fn (Builder $organization): Builder => $organization
                ->where('public_id', $request->query('organization')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }
    }

    protected function applySorting(Builder $query, Request $request): void
    {
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ((string) $request->query('sort', 'date')) {
            'reads' => $query->orderBy('total_reads', $direction),
            'title' => $query->orderBy('title', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        $query->orderBy('id', 'desc');
    }

    protected function findActiveArticle(string $publicId): Article
    {
        $article = Article::query()->where('public_id', $publicId)->first();

        if ($article === null) {
            throw new ApiException('Article was not found.', 404);
        }

        return $article;
    }

    protected function ensureStatus(Article $article, array $allowedStatuses, string $message): void
    {
        if (! in_array($article->status, $allowedStatuses, true)) {
            throw new ApiException($message, 422);
        }
    }

    protected function history(Article $article, array $actions)
    {
        return DB::table('admin_logs')
            ->join('users as admins', 'admins.id', '=', 'admin_logs.admin_id')
            ->where('admin_logs.entity_type', 'article')
            ->where('admin_logs.entity_id', $article->public_id)
            ->whereIn('admin_logs.action', $actions)
            ->latest('admin_logs.created_at')
            ->limit(20)
            ->get(['admin_logs.action', 'admin_logs.metadata', 'admin_logs.created_at', 'admins.full_name as admin_name']);
    }

    protected function log(User $admin, Article $article, string $action, Request $request, array $metadata = []): void
    {
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => 'article',
            'entity_id' => $article->public_id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'created_at' => now(),
        ]);
    }
}
