<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\OrganizationDocument;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Services\Compliance\IrsOrganizationVerificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationRegistrationService
{
    public function __construct(
        protected OtpService $otpService,
        protected IrsOrganizationVerificationService $irsVerificationService,
    ) {
    }

    public function register(array $data, UploadedFile $certificatePdf): array
    {
        $irsVerification = $this->irsVerificationService->verify($data['organization_name'], $data['tax_id']);

        if (! ($irsVerification['matched'] ?? false)) {
            throw new ApiException(
                'We could not verify this organization name and tax ID with IRS records.',
                422,
                ['tax_id' => [$irsVerification['reason'] ?? 'Please check the organization name and tax ID.']],
            );
        }

        try {
            return DB::transaction(function () use ($data, $certificatePdf, $irsVerification): array {
                $roleId = $this->resolveOrganizationRoleId();

                $user = User::create([
                    'public_id' => (string) Str::uuid(),
                    'role_id' => $roleId,
                    'full_name' => $data['organization_name'],
                    'username' => $this->generateUsername($data['email'], 'org'),
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'is_active' => false,
                    'failed_login_attempts' => 0,
                ]);

                $certificatePath = $certificatePdf->storeAs(
                    'organization-certificates/'.$user->public_id,
                    (string) Str::uuid().'.pdf',
                    'local',
                );

                $profile = OrganizationProfile::create([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'organization_name' => $data['organization_name'],
                    'website' => $data['website'] ?? null,
                    'tax_id' => $data['tax_id'],
                    'certificate_file' => $certificatePath,
                    'irs_verified' => true,
                    'verification_status' => 'pending',
                    'metadata' => [
                        'irs_verification' => $irsVerification,
                    ],
                ]);

                OrganizationDocument::create([
                    'organization_profile_id' => $profile->id,
                    'type' => 'certificate',
                    'file_path' => $certificatePath,
                    'mime_type' => $certificatePdf->getClientMimeType(),
                    'file_size' => $certificatePdf->getSize(),
                    'uploaded_by' => $user->id,
                    'uploaded_at' => now(),
                ]);

                $otpPayload = $this->otpService->issueForUser($user);

                return [$user->refresh(), $profile->refresh(), $otpPayload];
            }, 3);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'organization' => ['An organization account with the provided email or tax ID already exists.'],
            ]);
        }
    }

    protected function resolveOrganizationRoleId(): int
    {
        $existingRoleId = DB::table('roles')->where('name', 'organization')->value('id');

        if ($existingRoleId !== null) {
            return (int) $existingRoleId;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => 'organization',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function generateUsername(string $email, string $prefix): string
    {
        $base = Str::of($prefix.'_'.Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->substr(0, 40)
            ->value();

        if ($base === '') {
            $base = $prefix.'_account';
        }

        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $tail = '_'.$suffix++;
            $username = Str::limit($base, 50 - strlen($tail), '').$tail;
        }

        return $username;
    }
}
