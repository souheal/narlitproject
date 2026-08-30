<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminRolePermissionSeeder::class);

        if ($this->command !== null) {
            $this->command->warn('No super admin credentials were seeded. Use php artisan admin:create-superadmin.');
        }
    }
}
