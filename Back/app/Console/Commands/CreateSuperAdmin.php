<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AdminRolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-superadmin
        {--email= : Super admin email address}
        {--password= : Super admin password}
        {--name= : Super admin full name}
        {--username= : Super admin username}
        {--phone= : Super admin phone number for MFA}';

    protected $description = 'Create or update the initial super admin without hardcoded credentials.';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Full name', 'Super Admin')));
        $username = trim((string) ($this->option('username') ?: $this->ask('Username', $this->defaultUsername($email))));
        $phone = trim((string) ($this->option('phone') ?: $this->ask('Phone number for MFA')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'username' => $username,
            'phone' => $phone,
            'password' => $password,
        ], [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => AdminRolePermissionSeeder::class]);

        $superAdminRole = Role::findByName('super_admin', 'web');

        $usernameOwner = User::query()
            ->where('username', $username)
            ->where('email', '!=', $email)
            ->exists();

        if ($usernameOwner) {
            $this->error('The selected username is already assigned to another account.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $attributes = [
            'public_id' => $user->public_id ?: (string) Str::uuid(),
            'role_id' => $superAdminRole->id,
            'full_name' => $name,
            'username' => $username,
            'phone' => $phone,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'first_login_mfa_completed_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ];

        if (Schema::hasColumn('users', 'gender')) {
            $attributes['gender'] = $user->gender ?: 'other';
        }

        if (Schema::hasColumn('users', 'mfa_enrolled_at')) {
            $attributes['mfa_enrolled_at'] = null;
        }

        $user->forceFill($attributes)->save();
        $user->assignRole($superAdminRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Super admin account is ready.');

        return self::SUCCESS;
    }

    private function defaultUsername(string $email): string
    {
        $localPart = Str::before($email, '@') ?: 'super-admin';

        return Str::of($localPart)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-_')
            ->limit(50, '')
            ->whenEmpty(fn () => Str::of('super-admin'))
            ->toString();
    }
}
