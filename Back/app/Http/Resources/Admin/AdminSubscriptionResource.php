<?php

namespace App\Http\Resources\Admin;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        return [
            'public_id' => $subscription->public_id,
            'subscriber' => [
                'public_id' => $subscription->user?->public_id,
                'name' => $subscription->user?->full_name,
                'email' => $subscription->user?->email,
            ],
            'plan' => $subscription->plan,
            'amount' => number_format((float) $subscription->amount, 2, '.', ''),
            'currency' => $subscription->currency,
            'status' => $subscription->status,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'renews_or_expires_at' => $subscription->expires_at?->toIso8601String(),
            'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            'stripe_customer_id' => $subscription->stripe_customer_id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'stripe_links' => [
                'customer' => $this->stripeLink('customers', $subscription->stripe_customer_id),
                'subscription' => $this->stripeLink('subscriptions', $subscription->stripe_subscription_id),
            ],
        ];
    }

    protected function stripeLink(string $section, ?string $id): ?string
    {
        if ($id === null || $id === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $id)) {
            return null;
        }

        return "https://dashboard.stripe.com/{$section}/{$id}";
    }
}
