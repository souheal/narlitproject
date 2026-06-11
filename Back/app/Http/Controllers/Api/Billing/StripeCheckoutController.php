<?php

namespace App\Http\Controllers\Api\Billing;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Models\User;
use App\Services\Billing\StripeCheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StripeCheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StripeCheckoutService $stripeCheckoutService,
    ) {
    }

    public function store(CreateCheckoutSessionRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            throw new ApiException('No registration was found for the provided email address.', 404);
        }

        $checkout = $this->stripeCheckoutService->createForVerifiedUser(
            $user,
            $request->validated('subscription_plan') ?? 'monthly',
        );

        $message = ($checkout['mode'] ?? null) === 'fake'
            ? 'Payment completed. Registration is now complete and the account is active.'
            : 'Checkout session created successfully.';

        return $this->success($message, $checkout);
    }
}
