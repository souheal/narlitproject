<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrsExemptOrganization extends Model
{
    protected $fillable = [
        'ein',
        'organization_name',
        'normalized_name',
        'city',
        'state',
        'country',
        'subsection',
        'classification',
        'ruling_date',
        'deductibility',
        'foundation_code',
        'activity_code',
        'organization_code',
        'source',
        'imported_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'raw' => 'array',
        ];
    }
}
