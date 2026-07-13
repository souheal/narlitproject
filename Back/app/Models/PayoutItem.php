<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    protected $fillable = [
        'payout_batch_id',
        'organization_profile_id',
        'engagement_score',
        'payout_amount',
        'total_reads',
        'total_points',
        'stripe_transfer_id',
        'transfer_status',
        'transferred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'engagement_score' => 'decimal:4',
            'payout_amount' => 'decimal:2',
            'transferred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    public function organizationProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class);
    }
}
