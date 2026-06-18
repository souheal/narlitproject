<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name'       => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exists = DB::table('users')->where('username', 'superadmin')->exists();

        if ($exists) {
            return;
        }

        DB::table('users')->insert([
            'public_id'                   => (string) Str::uuid(),
            'role_id'                     => $roleId,
            'full_name'                   => 'Super Admin',
            'username'                    => 'superadmin',
            'email'                       => 'superadmin@narlit.com',
            'phone'                       => '+10000000000',
            'password'                    => Hash::make('Admin@Narlit2026!'),
            'is_active'                   => true,
            'email_verified_at'           => now(),
            'first_login_mfa_completed_at'=> now(),
            'failed_login_attempts'       => 0,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }
}
