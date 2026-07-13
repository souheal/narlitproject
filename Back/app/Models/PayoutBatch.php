<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    protected $fillable = [
        'public_id',
        'batch_month',
        'total_pool',
        'total_distributed',
        'total_organizations',
        'status',
        'processed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'batch_month' => 'date',
            'total_pool' => 'decimal:2',
            'total_distributed' => 'decimal:2',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }
}
