<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'public_id',
        'role_id',
        'full_name',
        'username',
        'email',
        'phone',
        'password',
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
        'is_active',
        'phone_mfa_code',
        'phone_mfa_expires_at',
        'phone_mfa_verified_at',
        'first_login_mfa_completed_at',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'phone_mfa_code',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'phone_mfa_expires_at' => 'datetime',
            'phone_mfa_verified_at' => 'datetime',
            'first_login_mfa_completed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
