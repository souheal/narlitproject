<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDocument extends Model
{
    protected $fillable = [
        'organization_profile_id',
        'type',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function organizationProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizationProfile::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
