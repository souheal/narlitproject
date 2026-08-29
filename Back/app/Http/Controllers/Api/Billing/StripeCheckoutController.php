<?php

namespace App\Http\Controllers\Api\Billing;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Models\User;
use App\Services\Billing\CheckoutContinuationTokenService;
use App\Services\Billing\StripeCheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StripeCheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StripeCheckoutService $stripeCheckoutService,
        protected CheckoutContinuationTokenService $checkoutTokens,
    ) {
    }

    public function store(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $reservation = $this->checkoutTokens->reserveForCheckout(
            $request->validated('email'),
            $request->validated('checkout_token'),
        );

        if ($reservation === null) {
            return $this->neutralCheckoutResponse();
        }

        if (($reservation['replayed'] ?? false) === true) {
            return $this->success($reservation['message'], $reservation['data']);
        }

        /** @var User $user */
        $user = $reservation['user'];

        try {
            $checkout = $this->stripeCheckoutService->createForVerifiedUser(
                $user,
                $request->validated('subscription_plan') ?? 'monthly',
            );
        } catch (ApiException $exception) {
            $this->checkoutTokens->releaseCheckoutReservation($user);

            if ($this->isAccountEligibilityFailure($exception)) {
                return $this->neutralCheckoutResponse();
            }

            throw $exception;
        }

        $message = ($checkout['mode'] ?? null) === 'fake'
            ? 'Payment completed. Registration is now complete and the account is active.'
            : 'Checkout session created successfully.';

        $this->checkoutTokens->storeCheckoutResponse($user, $message, $checkout);

        return $this->success($message, $checkout);
    }

    protected function neutralCheckoutResponse(): JsonResponse
    {
        return $this->success('Checkout request received. If eligible, checkout can continue.', [
            'next_step' => 'check_registration_status',
        ], 202);
    }

    protected function isAccountEligibilityFailure(ApiException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'OTP verification is required before starting checkout.',
            'An active subscription already exists for this account.',
        ], true);
    }
}
