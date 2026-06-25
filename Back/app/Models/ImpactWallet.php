<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpactWallet extends Model
{
    protected $fillable = [
        'user_id',
        'total_impact_amount',
        'total_articles_read',
        'total_points',
        'total_organizations_supported',
    ];

    protected function casts(): array
    {
        return [
            'total_impact_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
