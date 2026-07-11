<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = DB::table('roles')->where('name', 'user')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name'       => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $email = 'user@narlit.com';

        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        $userId = DB::table('users')->insertGetId([
            'public_id'                    => (string) Str::uuid(),
            'role_id'                      => $roleId,
            'full_name'                    => 'Narlit User',
            'username'                     => 'narlituser',
            'email'                        => $email,
            'phone'                        => '+15550000001',
            'password'                     => Hash::make('User@Narlit2026!'),
            'is_active'                    => true,
            'email_verified_at'            => now(),
            'first_login_mfa_completed_at' => now(),
            'failed_login_attempts'        => 0,
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);

        DB::table('subscriptions')->insert([
            'public_id'              => (string) Str::uuid(),
            'user_id'                => $userId,
            'stripe_customer_id'     => 'cus_test_narlit',
            'stripe_subscription_id' => 'sub_test_narlit',
            'plan'                   => 'yearly',
            'amount'                 => 9600,
            'status'                 => 'active',
            'started_at'             => now(),
            'expires_at'             => now()->addYear(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }
}
