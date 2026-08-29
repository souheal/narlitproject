<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AdminSubscriptionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        return [
            'subscription' => new AdminSubscriptionResource($subscription),
            'payments' => $subscription->payments->map(fn (Payment $payment): array => [
                'public_id' => $payment->public_id,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'stripe_fee' => number_format((float) $payment->stripe_fee, 2, '.', ''),
                'net_amount' => number_format((float) $payment->net_amount, 2, '.', ''),
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'refunded_at' => $payment->refunded_at?->toIso8601String(),
                'stripe_payment_intent' => $this->canViewStripeIdentifiers($request) ? $payment->stripe_payment_intent : null,
                'stripe_invoice_id' => $this->canViewStripeIdentifiers($request) ? $payment->stripe_invoice_id : null,
                'stripe_links' => [
                    'payment' => $this->canViewStripeIdentifiers($request) ? $this->stripePaymentLink($payment->stripe_payment_intent) : null,
                    'invoice' => $this->canViewStripeIdentifiers($request) ? $this->stripeInvoiceLink($payment->stripe_invoice_id) : null,
                ],
            ])->all(),
            'admin_action_history' => $subscription->getAttribute('admin_action_history')
                ->map(fn (object $log): array => [
                    'action' => $log->action,
                    'admin_name' => $log->admin_name,
                    'metadata' => is_string($log->metadata ?? null) ? json_decode($log->metadata, true) : null,
                    'created_at' => Carbon::parse($log->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    protected function stripePaymentLink(?string $id): ?string
    {
        if ($id === null || $id === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $id)) {
            return null;
        }

        return "https://dashboard.stripe.com/payments/{$id}";
    }

    protected function canViewStripeIdentifiers(Request $request): bool
    {
        if (! Schema::hasTable('permissions')) {
            return false;
        }

        return (bool) $request->user()?->can('payments.refund');
    }

    protected function stripeInvoiceLink(?string $id): ?string
    {
        if ($id === null || $id === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $id)) {
            return null;
        }

        return "https://dashboard.stripe.com/invoices/{$id}";
    }
}
