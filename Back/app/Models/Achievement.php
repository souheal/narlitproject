<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'icon',
        'category',
        'target',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'points' => 'integer',
        ];
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
