<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'subscriptions.view', 'subscriptions.cancel', 'subscriptions.refund',
        'payments.view', 'payments.refund',
        'payouts.view', 'payouts.generate', 'payouts.execute', 'payouts.retry', 'payouts.cancel',
        'articles.view', 'articles.moderate', 'articles.approve', 'articles.reject', 'articles.request_changes', 'articles.feature', 'articles.archive', 'articles.restore',
        'organizations.view', 'organizations.review', 'organizations.approve', 'organizations.reject',
        'users.view', 'users.suspend', 'users.activate', 'users.reset_password', 'users.reset_mfa', 'users.revoke_tokens',
        'settings.view', 'settings.update',
        'audit.view', 'audit.export',
        'analytics.view',
        'admins.view', 'admins.manage_roles', 'admins.create', 'admins.update', 'admins.disable',
    ];

    public const ROLE_PERMISSIONS = [
        'admin_finance' => [
            'subscriptions.view', 'subscriptions.cancel', 'subscriptions.refund',
            'payments.view', 'payments.refund',
            'payouts.view', 'payouts.generate', 'payouts.execute', 'payouts.retry', 'payouts.cancel',
            'analytics.view', 'audit.view',
        ],
        'admin_content' => [
            'articles.view', 'articles.moderate', 'articles.approve', 'articles.reject', 'articles.request_changes', 'articles.feature', 'articles.archive', 'articles.restore',
            'organizations.view', 'organizations.review', 'organizations.approve', 'organizations.reject',
            'analytics.view',
        ],
        'admin_users' => [
            'users.view', 'users.suspend', 'users.activate', 'users.reset_password', 'users.reset_mfa', 'users.revoke_tokens',
            'audit.view',
        ],
        'admin_settings' => [
            'settings.view', 'settings.update', 'subscriptions.view', 'audit.view',
        ],
        'admin_readonly' => [
            'users.view', 'organizations.view', 'articles.view', 'subscriptions.view', 'payments.view', 'payouts.view', 'analytics.view', 'audit.view', 'settings.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('roles')->whereNull('guard_name')->update(['guard_name' => 'web']);

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        User::query()
            ->where('email', 'superadmin@narlit.com')
            ->orWhere('username', 'superadmin')
            ->first()
            ?->assignRole('super_admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
