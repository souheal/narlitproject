<?php

namespace Tests\Feature;

use App\Models\OrganizationProfile;
use App\Models\IrsExemptOrganization;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\PhoneMfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationRegistrationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        Storage::fake('local');
    }

    public function test_organization_registers_with_irs_match_certificate_pdf_and_email_otp(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $response = $this->post('/api/v1/auth/organization/register', [
            'organization_name' => 'Athar Foundation',
            'email' => 'org@test.com',
            'phone' => '09376635271',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'website' => 'https://athar.example',
            'tax_id' => '12-3456789',
            'certificate_pdf' => UploadedFile::fake()->create('certificate.pdf', 128, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.next_step', 'verify_email')
            ->assertJsonPath('data.organization.organization_name', 'Athar Foundation')
            ->assertJsonPath('data.organization.tax_id', '123456789')
            ->assertJsonPath('data.organization.irs_verified', true)
            ->assertJsonPath('data.organization.verification_status', 'pending');

        $user = User::query()->where('email', 'org@test.com')->firstOrFail();
        $profile = OrganizationProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('organization', DB::table('roles')->where('id', $user->role_id)->value('name'));
        $this->assertSame('123456789', $profile->tax_id);
        Storage::disk('local')->assertExists($profile->certificate_file);
        $this->assertDatabaseHas('organization_documents', [
            'organization_profile_id' => $profile->id,
            'type' => 'certificate',
            'file_path' => $profile->certificate_file,
        ]);
        $this->assertNotNull(app(OtpService::class)->previewForUser($user));
        $this->assertFalse($user->is_active);
        $this->assertNotNull($profile->metadata['irs_verification'] ?? null);
    }

    public function test_organization_email_otp_moves_to_pending_review(): void
    {
        $this->seedIrsOrganization('987654321', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', [
            'organization_name' => 'Athar Foundation',
            'email' => 'review@test.com',
            'phone' => '09376635271',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tax_id' => '987654321',
            'certificate_pdf' => UploadedFile::fake()->create('certificate.pdf', 128, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $user = User::query()->where('email', 'review@test.com')->firstOrFail();
        $otp = app(OtpService::class)->previewForUser($user);

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'review@test.com',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'pending_review')
            ->assertJsonPath('data.organization.verification_status', 'pending');

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertFalse($user->is_active);
    }

    public function test_organization_registration_rejects_irs_name_tax_id_mismatch(): void
    {
        $this->seedIrsOrganization('123456789', 'Different Legal Charity');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'We could not verify this organization name and tax ID with IRS records.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organization_profiles', 0);
        $this->assertDatabaseCount('organization_documents', 0);
    }

    public function test_organization_registration_requires_pdf_certificate(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $payload = $this->validPayload([
            'certificate_pdf' => UploadedFile::fake()->create('certificate.txt', 10, 'text/plain'),
        ]);

        $this->post('/api/v1/auth/organization/register', $payload, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['certificate_pdf']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organization_profiles', 0);
    }

    public function test_organization_registration_rejects_duplicate_email_and_tax_id(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'tax_id']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organization_profiles', 1);
        $this->assertDatabaseCount('organization_documents', 1);
    }

    public function test_organization_registration_rejects_missing_required_fields(): void
    {
        $this->post('/api/v1/auth/organization/register', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'organization_name',
                'email',
                'phone',
                'password',
                'tax_id',
                'certificate_pdf',
            ]);
    }

    public function test_irs_eo_bmf_import_command_imports_official_csv_shape(): void
    {
        $path = storage_path('app/test-irs-eo-bmf.csv');
        file_put_contents($path, implode("\n", [
            'EIN,NAME,CITY,STATE,SUBSECTION,CLASSIFICATION,RULING,DEDUCTIBILITY,FOUNDATION,ACTIVITY,ORGANIZATION',
            '123456789,Athar Foundation,New York,NY,03,1000,202001,1,15,000000000,1',
        ]));

        $this->artisan('irs:import-eo-bmf', [
            '--path' => [$path],
            '--truncate' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('irs_exempt_organizations', [
            'ein' => '123456789',
            'organization_name' => 'Athar Foundation',
            'source' => 'irs_eo_bmf',
        ]);

        @unlink($path);
    }

    public function test_admin_can_approve_email_verified_organization_registration(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $user = User::query()->where('email', 'org@test.com')->firstOrFail();
        $otp = app(OtpService::class)->previewForUser($user);

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'org@test.com',
            'otp' => $otp,
        ])->assertOk();

        $profile = OrganizationProfile::query()->where('user_id', $user->id)->firstOrFail();
        Sanctum::actingAs($this->adminUser());

        $this->postJson("/api/v1/admin/organizations/{$profile->public_id}/approve")
            ->assertOk()
            ->assertJsonPath('data.organization.verification_status', 'approved');

        $this->assertTrue($user->refresh()->is_active);
        $this->assertSame('approved', $profile->refresh()->verification_status);
        $this->assertNotNull($profile->reviewed_by);
        $this->assertNotNull($profile->reviewed_at);

        $firstLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'org@test.com',
            'password' => 'Password123!',
        ]);

        $firstLogin
            ->assertOk()
            ->assertJsonPath('data.next_step', 'phone_mfa_required')
            ->assertJsonMissingPath('data.token');

        $mfaCode = app(PhoneMfaService::class)->previewForUser($user->refresh());

        $this->assertNotNull($mfaCode);

        $this->postJson('/api/v1/auth/verify-phone-mfa', [
            'email' => 'org@test.com',
            'code' => $mfaCode,
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', 'completed')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_admin_can_list_pending_organization_registrations(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/v1/admin/organizations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.organizations.data.0.organization_name', 'Athar Foundation')
            ->assertJsonPath('data.organizations.data.0.verification_status', 'pending');
    }

    public function test_admin_cannot_approve_organization_without_certificate_document(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $user = User::query()->where('email', 'org@test.com')->firstOrFail();
        $otp = app(OtpService::class)->previewForUser($user);

        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'org@test.com',
            'otp' => $otp,
        ])->assertOk();

        $profile = OrganizationProfile::query()->where('user_id', $user->id)->firstOrFail();
        DB::table('organization_documents')->where('organization_profile_id', $profile->id)->delete();

        Sanctum::actingAs($this->adminUser());

        $this->postJson("/api/v1/admin/organizations/{$profile->public_id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'The organization must upload a certificate of incorporation before approval.');

        $this->assertFalse($user->refresh()->is_active);
    }

    public function test_admin_can_reject_organization_registration_with_reason(): void
    {
        $this->seedIrsOrganization('123456789', 'Athar Foundation');

        $this->post('/api/v1/auth/organization/register', $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();

        $profile = OrganizationProfile::query()->firstOrFail();
        Sanctum::actingAs($this->adminUser());

        $this->postJson("/api/v1/admin/organizations/{$profile->public_id}/reject", [
            'reason' => 'Certificate details do not match the application.',
        ])
            ->assertOk()
            ->assertJsonPath('data.organization.verification_status', 'rejected')
            ->assertJsonPath('data.organization.rejection_reason', 'Certificate details do not match the application.');

        $this->assertFalse($profile->user->refresh()->is_active);
        $this->assertSame('rejected', $profile->refresh()->verification_status);
    }

    private function createTestSchema(): void
    {
        Schema::dropIfExists('organization_documents');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('irs_exempt_organizations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('irs_exempt_organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('ein')->unique();
            $table->string('organization_name');
            $table->string('normalized_name')->index();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('subsection')->nullable();
            $table->string('classification')->nullable();
            $table->string('ruling_date')->nullable();
            $table->string('deductibility')->nullable();
            $table->string('foundation_code')->nullable();
            $table->string('activity_code')->nullable();
            $table->string('organization_code')->nullable();
            $table->string('source')->default('irs_eo_bmf');
            $table->timestamp('imported_at');
            $table->json('raw')->nullable();
            $table->timestamps();
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
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone_mfa_code')->nullable();
            $table->timestamp('phone_mfa_expires_at')->nullable();
            $table->timestamp('phone_mfa_verified_at')->nullable();
            $table->timestamp('first_login_mfa_completed_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('organization_name');
            $table->string('website')->nullable();
            $table->string('landline')->nullable();
            $table->string('tax_id')->unique();
            $table->string('certificate_file');
            $table->boolean('irs_verified')->default(false);
            $table->string('verification_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('stripe_connect_account_id')->nullable();
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_profile_id')->constrained('organization_profiles');
            $table->string('type');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
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
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Athar Foundation',
            'email' => 'org@test.com',
            'phone' => '09376635271',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'website' => 'https://athar.example',
            'tax_id' => '12-3456789',
            'certificate_pdf' => UploadedFile::fake()->create('certificate.pdf', 128, 'application/pdf'),
        ], $overrides);
    }

    private function seedIrsOrganization(string $ein, string $name): void
    {
        IrsExemptOrganization::create([
            'ein' => $ein,
            'organization_name' => $name,
            'normalized_name' => strtolower($name),
            'source' => 'irs_eo_bmf',
            'imported_at' => now(),
        ]);
    }

    private function adminUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'public_id' => (string) str()->uuid(),
            'role_id' => $roleId,
            'full_name' => 'Admin User',
            'username' => 'admin_user',
            'email' => 'admin@test.com',
            'phone' => '09376635272',
            'password' => 'Password123!',
            'email_verified_at' => now(),
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }
}
