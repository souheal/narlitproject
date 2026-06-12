<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function userHasRequiredAccess(User $user): bool
    {
        if ($this->userIsOrganization($user)) {
            return $user->organizationProfile?->verification_status === 'approved';
        }

        return $this->userHasActiveSubscription($user);
    }

    public function userHasActiveSubscription(User $user): bool
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function activateUser(User $user): void
    {
        $user->forceFill(['is_active' => true])->save();
    }

    public function deactivateUser(User $user): void
    {
        $user->forceFill(['is_active' => false])->save();
    }

    public function syncSubscription(User $user, array $attributes): Subscription
    {
        $subscription = Subscription::query()->firstOrNew([
            'stripe_subscription_id' => $attributes['stripe_subscription_id'],
        ]);

        if (! $subscription->exists) {
            $subscription->public_id = $attributes['public_id'];
        }

        $subscription->fill($attributes);
        $subscription->user_id = $user->id;
        $subscription->save();

        Subscription::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'updated_at' => now(),
            ]);

        if ($subscription->status === 'active' && $subscription->expires_at?->isFuture() && $user->email_verified_at !== null) {
            $this->activateUser($user);
        } else {
            $this->deactivateUser($user);
        }

        return $subscription->refresh();
    }

    protected function userIsOrganization(User $user): bool
    {
        return DB::table('roles')->where('id', $user->role_id)->value('name') === 'organization';
    }
}
