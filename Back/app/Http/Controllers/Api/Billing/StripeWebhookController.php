<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeWebhookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StripeWebhookService $stripeWebhookService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret', '');

        if ($secret === '') {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json([
                'message' => 'Webhook processing is temporarily unavailable.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException) {
            return $this->invalidWebhookResponse();
        } catch (SignatureVerificationException) {
            return $this->invalidWebhookResponse();
        }

        try {
            $result = $this->stripeWebhookService->handle(
                $event,
                hash('sha256', $payload),
            );
        } catch (Throwable) {
            return response()->json([
                'message' => 'Webhook processing failed.',
            ], 500);
        }

        return match ($result) {
            'duplicate' => $this->success('Webhook event already processed.'),
            'ignored' => $this->success('Webhook event ignored.'),
            default => $this->success('Webhook processed.'),
        };
    }

    protected function invalidWebhookResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid webhook request.',
        ], 400);
    }
}
