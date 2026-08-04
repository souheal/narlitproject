<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminRolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.enforce_in_tests', true);
        $this->createTestSchema();
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_permissions_and_roles_seed_idempotently(): void
    {
        $this->seed(AdminRolePermissionSeeder::class);

        $this->assertSame(count(AdminRolePermissionSeeder::PERMISSIONS), DB::table('permissions')->count());
        $this->assertDatabaseHas('roles', ['name' => 'super_admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin_finance', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin_content', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin_users', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin_settings', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'admin_readonly', 'guard_name' => 'web']);
    }

    public function test_super_admin_can_manage_admin_roles(): void
    {
        $superAdmin = $this->admin('super-admin@test.com', ['super_admin']);
        $target = $this->admin('finance-admin@test.com', ['admin_finance']);
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/v1/admin/admins')
            ->assertOk()
            ->assertJsonPath('data.admins.0.roles.0', 'super_admin');

        $this->putJson("/api/v1/admin/admins/{$target->public_id}/roles", [
            'roles' => ['admin_content'],
        ])
            ->assertOk()
            ->assertJsonPath('data.admin.roles.0', 'admin_content');

        $this->assertTrue($target->refresh()->hasRole('admin_content'));
        $this->assertFalse($target->hasRole('admin_finance'));
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $superAdmin->id,
            'action' => 'admin.roles_updated',
            'entity_id' => $target->public_id,
        ]);
    }

    public function test_non_super_admin_and_readonly_admin_cannot_assign_roles(): void
    {
        $contentAdmin = $this->admin('content-admin@test.com', ['admin_content']);
        $readonlyAdmin = $this->admin('readonly-admin@test.com', ['admin_readonly']);
        $target = $this->admin('target-admin@test.com', ['admin_users']);

        Sanctum::actingAs($contentAdmin);
        $this->putJson("/api/v1/admin/admins/{$target->public_id}/roles", [
            'roles' => ['super_admin'],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');

        Sanctum::actingAs($readonlyAdmin);
        $this->putJson("/api/v1/admin/admins/{$target->public_id}/roles", [
            'roles' => ['admin_finance'],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    public function test_cannot_remove_last_active_super_admin(): void
    {
        $superAdmin = $this->admin('only-super@test.com', ['super_admin']);
        Sanctum::actingAs($superAdmin);

        $this->putJson("/api/v1/admin/admins/{$superAdmin->public_id}/roles", [
            'roles' => ['admin_readonly'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove the last active super admin.');
    }

    public function test_role_changes_take_effect_on_next_request(): void
    {
        $superAdmin = $this->admin('main-super@test.com', ['super_admin']);
        $target = $this->admin('promoted@test.com', ['admin_readonly']);
        Sanctum::actingAs($superAdmin);

        $this->putJson("/api/v1/admin/admins/{$target->public_id}/roles", [
            'roles' => ['super_admin'],
        ])->assertOk();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($target->refresh());

        $this->getJson('/api/v1/admin/admins')->assertOk();

        Sanctum::actingAs($superAdmin);
        $this->putJson("/api/v1/admin/admins/{$target->public_id}/roles", [
            'roles' => ['admin_readonly'],
        ])->assertOk();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($target->refresh());

        $this->getJson('/api/v1/admin/admins')
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    private function admin(string $email, array $roles): User
    {
        $legacyRoleId = DB::table('roles')->where('name', 'admin')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

        $user = User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $legacyRoleId,
            'full_name' => str($email)->before('@')->headline()->toString(),
            'username' => str($email)->before('@')->replace('.', '_')->toString(),
            'email' => $email,
            'phone' => '+10000000000',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => true,
            'first_login_mfa_completed_at' => now(),
            'mfa_enrolled_at' => now(),
            'failed_login_attempts' => 0,
        ]);

        $user->assignRole($roles);

        return $user;
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('role_id')->constrained('roles');
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->timestamp('mfa_enrolled_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
