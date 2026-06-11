<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeWebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StripeWebhookService $stripeWebhookService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $this->stripeWebhookService->handle(
            $request->getContent(),
            (string) $request->header('Stripe-Signature', ''),
        );

        return $this->success('Webhook processed.');
    }
}
