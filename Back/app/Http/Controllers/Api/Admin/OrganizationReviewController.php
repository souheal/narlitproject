<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectOrganizationRequest;
use App\Models\OrganizationProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationReviewController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new ApiException('Please choose a valid organization status.', 422);
        }

        $organizations = OrganizationProfile::query()
            ->with('user')
            ->where('verification_status', $status)
            ->latest()
            ->paginate((int) min(max((int) $request->query('per_page', 15), 1), 100));

        return $this->success('Organizations retrieved successfully.', [
            'organizations' => $organizations->through(fn (OrganizationProfile $profile): array => $this->payload($profile)),
        ]);
    }

    public function approve(string $publicId, Request $request): JsonResponse
    {
        $profile = $this->findProfile($publicId);

        if ($profile->user->email_verified_at === null) {
            throw new ApiException('The organization must verify its email before approval.', 422);
        }

        if (! $profile->irs_verified) {
            throw new ApiException('The organization must pass IRS verification before approval.', 422);
        }

        if ($profile->certificate_file === '' || ! $profile->documents()->where('type', 'certificate')->exists()) {
            throw new ApiException('The organization must upload a certificate of incorporation before approval.', 422);
        }

        DB::transaction(function () use ($profile, $request): void {
            $profile->forceFill([
                'verification_status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $profile->user->forceFill([
                'is_active' => true,
            ])->save();
        }, 3);

        return $this->success('Organization approved successfully.', [
            'organization' => $this->payload($profile->refresh()),
        ]);
    }

    public function reject(string $publicId, RejectOrganizationRequest $request): JsonResponse
    {
        $profile = $this->findProfile($publicId);

        DB::transaction(function () use ($profile, $request): void {
            $profile->forceFill([
                'verification_status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ])->save();

            $profile->user->forceFill([
                'is_active' => false,
            ])->save();
        }, 3);

        return $this->success('Organization rejected successfully.', [
            'organization' => $this->payload($profile->refresh()),
        ]);
    }

    protected function findProfile(string $publicId): OrganizationProfile
    {
        $profile = OrganizationProfile::query()
            ->with('user')
            ->where('public_id', $publicId)
            ->first();

        if ($profile === null) {
            throw new ApiException('Organization registration was not found.', 404);
        }

        return $profile;
    }

    protected function payload(OrganizationProfile $profile): array
    {
        return [
            'public_id' => $profile->public_id,
            'organization_name' => $profile->organization_name,
            'email' => $profile->user->email,
            'irs_verified' => $profile->irs_verified,
            'verification_status' => $profile->verification_status,
            'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
            'rejection_reason' => $profile->rejection_reason,
        ];
    }
}
