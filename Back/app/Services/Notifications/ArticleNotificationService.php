<?php

namespace App\Services\Notifications;

use App\Models\Article;
use App\Models\Notification;
use Illuminate\Support\Str;

/**
 * In-app notifications sent to the organization that owns an article when an
 * admin moderates it. They land in the same `notifications` table members use,
 * so the organization reads them through /member/notifications.
 */
class ArticleNotificationService
{
    public function published(Article $article): void
    {
        $this->notifyOwner(
            $article,
            'article.published',
            'Your article is live',
            "\"{$article->title}\" was approved and is now visible to readers.",
            'View article',
            '✅',
        );
    }

    public function rejected(Article $article, string $reason): void
    {
        $this->notifyOwner(
            $article,
            'article.rejected',
            'Your article was not approved',
            "\"{$article->title}\": {$reason}",
            'See details',
            '❌',
        );
    }

    public function changesRequested(Article $article, string $reason): void
    {
        $this->notifyOwner(
            $article,
            'article.changes_requested',
            'Changes requested on your article',
            "\"{$article->title}\": {$reason}",
            'Edit article',
            '✏️',
        );
    }

    protected function notifyOwner(Article $article, string $type, string $title, string $body, string $actionLabel, string $icon): void
    {
        $userId = $article->organizationProfile()->value('user_id');

        if ($userId === null) {
            return;
        }

        Notification::query()->create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => Str::limit($body, 500),
            'action_url' => "/organization/articles/{$article->public_id}",
            'action_label' => $actionLabel,
            'icon' => $icon,
        ]);
    }
}
