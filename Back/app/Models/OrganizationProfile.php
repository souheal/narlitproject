<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'organization_name',
        'website',
        'landline',
        'tax_id',
        'certificate_file',
        'irs_verified',
        'verification_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'stripe_connect_account_id',
        'payouts_enabled',
        'charges_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'irs_verified' => 'boolean',
            'payouts_enabled' => 'boolean',
            'charges_enabled' => 'boolean',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function impactTransactions(): HasMany
    {
        return $this->hasMany(ImpactTransaction::class);
    }
}
