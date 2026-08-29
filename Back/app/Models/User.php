<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles {
        HasRoles::hasRole as protected spatieHasRole;
        HasRoles::getRoleNames as protected spatieGetRoleNames;
    }

    use Notifiable;

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
        'checkout_token_hash',
        'checkout_token_expires_at',
        'checkout_token_consumed_at',
        'checkout_replay_message',
        'checkout_replay_data',
        'is_active',
        'phone_mfa_code',
        'phone_mfa_expires_at',
        'phone_mfa_verified_at',
        'mfa_enrolled_at',
        'password_reset_otp_code',
        'password_reset_otp_expires_at',
        'password_reset_otp_verified_at',
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
        'checkout_token_hash',
        'checkout_token_expires_at',
        'checkout_token_consumed_at',
        'checkout_replay_message',
        'checkout_replay_data',
        'phone_mfa_code',
        'password_reset_otp_code',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'checkout_token_expires_at' => 'datetime',
            'checkout_token_consumed_at' => 'datetime',
            'checkout_replay_data' => 'array',
            'is_active' => 'boolean',
            'phone_mfa_expires_at' => 'datetime',
            'phone_mfa_verified_at' => 'datetime',
            'mfa_enrolled_at' => 'datetime',
            'password_reset_otp_expires_at' => 'datetime',
            'password_reset_otp_verified_at' => 'datetime',
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

    public function latestSubscriptionForAdmin(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('started_at');
    }

    public function paymentsForAdmin(): HasMany
    {
        return $this->hasMany(Payment::class)->latest()->limit(10);
    }

    public function organizationProfile(): HasOne
    {
        return $this->hasOne(OrganizationProfile::class);
    }

    public function articleReads(): HasMany
    {
        return $this->hasMany(ArticleRead::class);
    }

    public function impactTransactions(): HasMany
    {
        return $this->hasMany(ImpactTransaction::class);
    }

    public function impactWallet(): HasOne
    {
        return $this->hasOne(ImpactWallet::class);
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($this->permissionTablesExist()) {
            try {
                if ($this->spatieHasRole($roles, $guard)) {
                    return true;
                }
            } catch (\Throwable) {
                //
            }
        }

        if (! is_string($roles)) {
            return false;
        }

        return DB::table('roles')->where('id', $this->role_id)->value('name') === $roles;
    }

    public function isAdminAccount(): bool
    {
        if ($this->permissionTablesExist()) {
            try {
                if ($this->spatieHasRole(['super_admin', 'admin_finance', 'admin_content', 'admin_users', 'admin_settings', 'admin_readonly'])) {
                    return true;
                }
            } catch (\Throwable) {
                //
            }
        }

        return DB::table('roles')->where('id', $this->role_id)->value('name') === 'admin';
    }

    public function requiresMfa(): bool
    {
        if ($this->isAdminAccount()) {
            return true;
        }

        return $this->first_login_mfa_completed_at === null;
    }

    public function hasCompletedMfaEnrollment(): bool
    {
        if (array_key_exists('mfa_enrolled_at', $this->attributes)) {
            return $this->mfa_enrolled_at !== null;
        }

        return $this->first_login_mfa_completed_at !== null;
    }

    public function safeAdminRoleNames(): array
    {
        if (! $this->permissionTablesExist()) {
            return [];
        }

        try {
            return $this->spatieGetRoleNames()->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function safeAdminPermissionNames(): array
    {
        if (! $this->permissionTablesExist()) {
            return [];
        }

        try {
            return $this->getAllPermissions()->pluck('name')->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function permissionTablesExist(): bool
    {
        if (app()->runningUnitTests() && ! config('permission.enforce_in_tests', false)) {
            return false;
        }

        return Schema::hasTable('permissions')
            && Schema::hasTable('model_has_roles')
            && Schema::hasTable('role_has_permissions');
    }
}
