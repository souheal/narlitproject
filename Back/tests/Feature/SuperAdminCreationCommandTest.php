<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminCreationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_rbac_without_hardcoded_super_admin_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(AdminRolePermissionSeeder::PERMISSIONS), DB::table('permissions')->count());
        $this->assertSame(1, DB::table('roles')->where('name', 'super_admin')->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_create_super_admin_command_creates_hashed_idempotent_super_admin(): void
    {
        $password = 'VeryStrongPassword123!';

        $exitCode = Artisan::call('admin:create-superadmin', [
            '--email' => 'initial-super-admin@test.com',
            '--password' => $password,
            '--name' => 'Initial Super Admin',
            '--username' => 'initial_super_admin',
            '--phone' => '+15550001111',
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Super admin account is ready.', $output);
        $this->assertStringNotContainsString($password, $output);

        $user = User::query()->where('email', 'initial-super-admin@test.com')->firstOrFail();

        $this->assertSame('Initial Super Admin', $user->full_name);
        $this->assertSame('initial_super_admin', $user->username);
        $this->assertSame('+15550001111', $user->phone);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->first_login_mfa_completed_at);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => DB::table('roles')->where('name', 'super_admin')->value('id'),
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $secondExitCode = Artisan::call('admin:create-superadmin', [
            '--email' => 'initial-super-admin@test.com',
            '--password' => 'AnotherStrongPassword123!',
            '--name' => 'Initial Super Admin',
            '--username' => 'initial_super_admin',
            '--phone' => '+15550001111',
        ]);

        $this->assertSame(0, $secondExitCode);
        $this->assertSame(1, User::query()->where('email', 'initial-super-admin@test.com')->count());
        $this->assertSame(1, DB::table('model_has_roles')->where('model_id', $user->id)->count());
    }

    public function test_create_super_admin_command_does_not_expose_credentials_on_validation_failure(): void
    {
        $password = 'short';

        $exitCode = Artisan::call('admin:create-superadmin', [
            '--email' => 'not-an-email',
            '--password' => $password,
            '--name' => 'Initial Super Admin',
            '--username' => 'initial_super_admin',
            '--phone' => '+15550001111',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringNotContainsString($password, $output);
        $this->assertSame(0, User::query()->count());
    }
}
