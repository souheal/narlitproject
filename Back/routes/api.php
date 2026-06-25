<?php

use App\Http\Controllers\Api\Admin\OrganizationReviewController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\OtpVerificationController;
use App\Http\Controllers\Api\Auth\OrganizationRegistrationController;
use App\Http\Controllers\Api\Auth\PhoneMfaController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Billing\StripeCheckoutController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Member\MemberArticleController;
use App\Http\Controllers\Api\Member\MemberDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:auth.register');
        Route::post('/organization/register', [OrganizationRegistrationController::class, 'store'])->middleware('throttle:auth.organization.register');
        Route::post('/verify-otp', [OtpVerificationController::class, 'verify'])->middleware('throttle:auth.otp.verify');
        Route::post('/resend-otp', [OtpVerificationController::class, 'resend'])->middleware('throttle:auth.otp.resend');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth.login');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:auth.password.forgot');
        Route::post('/verify-reset-otp', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:auth.password.verify');
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->middleware('throttle:auth.password.reset');
        Route::post('/verify-phone-mfa', [PhoneMfaController::class, 'verify'])->middleware('throttle:auth.phone_mfa.verify');
        Route::post('/resend-phone-mfa', [PhoneMfaController::class, 'resend'])->middleware('throttle:auth.phone_mfa.resend');

        Route::middleware(['auth:sanctum', 'narlit.user.access'])->get('/me', function (Request $request) {
            return response()->json([
                'message' => 'Authenticated user retrieved.',
                'data' => [
                    'user' => $request->user(),
                ],
            ]);
        });

        Route::middleware('auth:sanctum')->post('/logout', [LoginController::class, 'destroy']);
    });

    Route::post('/billing/checkout', [StripeCheckoutController::class, 'store'])->middleware('throttle:billing.checkout');
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->middleware('throttle:billing.webhook');

    Route::middleware(['auth:sanctum', 'narlit.user.access'])->prefix('member')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'show']);
        Route::get('/articles', [MemberArticleController::class, 'index']);
        Route::post('/articles/{publicId}/read', [MemberArticleController::class, 'markRead']);
    });

    Route::middleware(['auth:sanctum', 'narlit.admin'])->prefix('admin')->group(function () {
        Route::get('/organizations', [OrganizationReviewController::class, 'index']);
        Route::post('/organizations/{publicId}/approve', [OrganizationReviewController::class, 'approve']);
        Route::post('/organizations/{publicId}/reject', [OrganizationReviewController::class, 'reject']);
    });
});
