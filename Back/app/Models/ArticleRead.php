<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleRead extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'article_id',
        'user_id',
        'read_percent',
        'reading_seconds',
        'points_earned',
        'counted_for_payout',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'counted_for_payout' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
