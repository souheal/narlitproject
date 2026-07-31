<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'categories',
        'followed_organizations',
        'email_notifications',
        'push_notifications',
        'email_new_articles',
        'email_new_from_supported',
        'email_impact_summary',
        'email_product_updates',
        'push_new_articles',
        'push_achievements',
        'push_payment_events',
        'digest_frequency',
        'email_digest',
        'theme',
        'language',
        'timezone',
        'monthly_reading_goal',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'followed_organizations' => 'array',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'email_new_articles' => 'boolean',
            'email_new_from_supported' => 'boolean',
            'email_impact_summary' => 'boolean',
            'email_product_updates' => 'boolean',
            'push_new_articles' => 'boolean',
            'push_achievements' => 'boolean',
            'push_payment_events' => 'boolean',
            'monthly_reading_goal' => 'integer',
            'onboarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
