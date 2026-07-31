<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'organization_profile_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'category',
        'status',
        'rejection_reason',
        'featured_at',
        'published_at',
        'read_time',
        'total_reads',
        'total_unique_reads',
        'total_reading_seconds',
        'total_points_generated',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'featured_at' => 'datetime',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organizationProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ArticleRead::class);
    }

    public function estimatedReadTimeMinutes(): int
    {
        $content = trim(strip_tags((string) $this->content));

        if ($content === '') {
            return 0;
        }

        $wordCount = Str::wordCount($content);

        if ($wordCount === 0) {
            return 0;
        }

        return max(1, (int) ceil($wordCount / 200));
    }
}
