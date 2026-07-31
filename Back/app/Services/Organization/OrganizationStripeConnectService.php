<?php

namespace App\Services\Organization;

use App\Models\OrganizationProfile;

class OrganizationStripeConnectService
{
    public function beginOnboarding(OrganizationProfile $organization): array
    {
        $metadata = $organization->metadata ?? [];

        if (empty($organization->stripe_connect_account_id)) {
            $organization->stripe_connect_account_id = 'acct_test_'.$organization->public_id;
        }

        $metadata['onboarding_started_at'] = now()->toIso8601String();
        $organization->metadata = $metadata;
        $organization->save();

        $configuredUrl = config('services.stripe.connect_onboarding_url');
        $onboardingUrl = is_string($configuredUrl) && $configuredUrl !== ''
            ? rtrim($configuredUrl, '/').'/'.$organization->public_id
            : 'https://connect.stripe.com/setup/e/'.$organization->public_id;

        return [
            'onboarding_url' => $onboardingUrl,
            'account_id' => $organization->stripe_connect_account_id,
        ];
    }

    public function status(OrganizationProfile $organization): array
    {
        $connected = ! empty($organization->stripe_connect_account_id);
        $payoutsEnabled = (bool) $organization->payouts_enabled;
        $chargesEnabled = (bool) $organization->charges_enabled;

        return [
            'connected' => $connected,
            'payouts_enabled' => $payoutsEnabled,
            'charges_enabled' => $chargesEnabled,
            'details_submitted' => (bool) ($organization->metadata['stripe_details_submitted'] ?? $chargesEnabled),
            'requires_action' => $connected && (! $payoutsEnabled || ! $chargesEnabled),
            'requirements_due' => (array) ($organization->metadata['stripe_requirements_due'] ?? []),
            'account_id' => $organization->stripe_connect_account_id,
        ];
    }
}
