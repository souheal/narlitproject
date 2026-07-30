<?php

use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminArticleModerationController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPayoutController;
use App\Http\Controllers\Api\Admin\AdminPlatformSettingsController;
use App\Http\Controllers\Api\Admin\AdminSubscriptionRevenueController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\OrganizationReviewController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\OrganizationRegistrationController;
use App\Http\Controllers\Api\Auth\OtpVerificationController;
use App\Http\Controllers\Api\Auth\PhoneMfaController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Billing\StripeCheckoutController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Member\MemberArticleController;
use App\Http\Controllers\Api\Member\MemberDashboardController;
use App\Http\Controllers\Api\SubscriptionPlanController;
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
    Route::get('/subscription/plans', [SubscriptionPlanController::class, 'index']);

    Route::middleware(['auth:sanctum', 'narlit.user.access'])->prefix('member')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'show']);
        Route::get('/articles', [MemberArticleController::class, 'index']);
        Route::post('/articles/{publicId}/read', [MemberArticleController::class, 'markRead']);
    });

    Route::middleware(['auth:sanctum', 'narlit.admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);
        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview']);
        Route::get('/analytics/timeseries', [AdminAnalyticsController::class, 'timeseries']);
        Route::get('/analytics/top-organizations', [AdminAnalyticsController::class, 'topOrganizations']);
        Route::get('/analytics/top-articles', [AdminAnalyticsController::class, 'topArticles']);
        Route::get('/analytics/categories', [AdminAnalyticsController::class, 'categories']);
        Route::get('/analytics/funnel', [AdminAnalyticsController::class, 'funnel']);
        Route::get('/analytics/cohorts', [AdminAnalyticsController::class, 'cohorts']);
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->middleware('throttle:admin.audit.export');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);
        Route::get('/settings', [AdminPlatformSettingsController::class, 'index']);
        Route::get('/settings/{group}', [AdminPlatformSettingsController::class, 'show']);
        Route::put('/settings/{group}', [AdminPlatformSettingsController::class, 'update'])->middleware('throttle:admin.sensitive');
        Route::get('/articles', [AdminArticleModerationController::class, 'index']);
        Route::get('/articles/{publicId}', [AdminArticleModerationController::class, 'show']);
        Route::post('/articles/{publicId}/approve', [AdminArticleModerationController::class, 'approve']);
        Route::post('/articles/{publicId}/reject', [AdminArticleModerationController::class, 'reject']);
        Route::post('/articles/{publicId}/request-changes', [AdminArticleModerationController::class, 'requestChanges']);
        Route::post('/articles/{publicId}/publish', [AdminArticleModerationController::class, 'publish']);
        Route::post('/articles/{publicId}/feature', [AdminArticleModerationController::class, 'feature']);
        Route::delete('/articles/{publicId}/feature', [AdminArticleModerationController::class, 'unfeature']);
        Route::post('/articles/{publicId}/archive', [AdminArticleModerationController::class, 'archive']);
        Route::post('/articles/{publicId}/restore', [AdminArticleModerationController::class, 'restore']);
        Route::get('/subscriptions/summary', [AdminSubscriptionRevenueController::class, 'summary']);
        Route::get('/subscriptions', [AdminSubscriptionRevenueController::class, 'index']);
        Route::get('/subscriptions/{publicId}', [AdminSubscriptionRevenueController::class, 'show']);
        Route::post('/subscriptions/{publicId}/cancel', [AdminSubscriptionRevenueController::class, 'cancel'])->middleware('throttle:admin.sensitive');
        Route::post('/payments/{publicId}/refund', [AdminSubscriptionRevenueController::class, 'refund'])->middleware('throttle:admin.sensitive');
        Route::get('/payouts/summary', [AdminPayoutController::class, 'summary']);
        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::post('/payouts/generate', [AdminPayoutController::class, 'generate'])->middleware('throttle:admin.sensitive');
        Route::get('/payouts/{publicId}', [AdminPayoutController::class, 'show']);
        Route::post('/payouts/{publicId}/execute', [AdminPayoutController::class, 'execute'])->middleware('throttle:admin.sensitive');
        Route::post('/payout-items/{id}/retry', [AdminPayoutController::class, 'retryItem'])->middleware('throttle:admin.sensitive');
        Route::post('/payouts/{publicId}/cancel', [AdminPayoutController::class, 'cancel'])->middleware('throttle:admin.sensitive');
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{publicId}', [AdminUserController::class, 'show']);
        Route::patch('/users/{publicId}/status', [AdminUserController::class, 'updateStatus']);
        Route::post('/users/{publicId}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->middleware('throttle:admin.users.password_reset');
        Route::post('/users/{publicId}/reset-mfa', [AdminUserController::class, 'resetMfa'])->middleware('throttle:admin.users.reset_mfa');
        Route::delete('/users/{publicId}/tokens', [AdminUserController::class, 'revokeTokens']);
        Route::get('/organizations', [OrganizationReviewController::class, 'index']);
        Route::post('/organizations/{publicId}/approve', [OrganizationReviewController::class, 'approve']);
        Route::post('/organizations/{publicId}/reject', [OrganizationReviewController::class, 'reject']);
    });
});
