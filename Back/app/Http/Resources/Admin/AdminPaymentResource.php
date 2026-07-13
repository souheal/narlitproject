<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;

        return [
            'public_id' => $payment->public_id,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'stripe_fee' => number_format((float) $payment->stripe_fee, 2, '.', ''),
            'net_amount' => number_format((float) $payment->net_amount, 2, '.', ''),
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'stripe_payment_intent' => $payment->stripe_payment_intent,
            'stripe_invoice_id' => $payment->stripe_invoice_id,
        ];
    }
}
