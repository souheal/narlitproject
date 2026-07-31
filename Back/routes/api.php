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
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Billing\StripeCheckoutController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\Member\MemberAccountController;
use App\Http\Controllers\Api\Member\MemberAchievementController;
use App\Http\Controllers\Api\Member\MemberArticleController;
use App\Http\Controllers\Api\Member\MemberBookmarkController;
use App\Http\Controllers\Api\Member\MemberDashboardController;
use App\Http\Controllers\Api\Member\MemberExploreController;
use App\Http\Controllers\Api\Member\MemberHistoryController;
use App\Http\Controllers\Api\Member\MemberImpactController;
use App\Http\Controllers\Api\Member\MemberNotificationController;
use App\Http\Controllers\Api\Member\MemberOnboardingController;
use App\Http\Controllers\Api\Member\MemberPreferenceController;
use App\Http\Controllers\Api\Member\MemberSubscriptionController;
use App\Http\Controllers\Api\Member\MemberSupportController;
use App\Http\Controllers\Api\Organization\OrganizationArticleController;
use App\Http\Controllers\Api\Organization\OrganizationDashboardController;
use App\Http\Controllers\Api\Organization\OrganizationPayoutController;
use App\Http\Controllers\Api\Organization\OrganizationStripeConnectController;
use App\Http\Controllers\Api\Public\PublicArticleController;
use App\Http\Controllers\Api\Public\PublicOrganizationController;
use App\Http\Controllers\Api\Public\PublicSearchController;
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

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::patch('/profile', [ProfileController::class, 'updateProfile']);
            Route::patch('/password', [ProfileController::class, 'updatePassword']);
        });
    });

    Route::post('/billing/checkout', [StripeCheckoutController::class, 'store'])->middleware('throttle:billing.checkout');
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->middleware('throttle:billing.webhook');
    Route::post('/contact', [ContactController::class, 'store']);

    Route::get('/articles/{publicId}', [PublicArticleController::class, 'show']);
    Route::get('/organizations', [PublicOrganizationController::class, 'index']);
    Route::get('/organizations/{publicId}', [PublicOrganizationController::class, 'show']);
    Route::get('/search', [PublicSearchController::class, 'index']);

    Route::middleware(['auth:sanctum', 'narlit.user.access'])->prefix('member')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'show']);
        Route::get('/articles', [MemberArticleController::class, 'index']);
        Route::post('/articles/{publicId}/read', [MemberArticleController::class, 'markRead']);

        Route::get('/notifications', [MemberNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [MemberNotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [MemberNotificationController::class, 'markAllRead']);
        Route::post('/notifications/{publicId}/read', [MemberNotificationController::class, 'markRead']);

        Route::get('/bookmarks', [MemberBookmarkController::class, 'index']);
        Route::post('/bookmarks/{articlePublicId}', [MemberBookmarkController::class, 'store']);
        Route::delete('/bookmarks/{articlePublicId}', [MemberBookmarkController::class, 'destroy']);

        Route::get('/achievements', [MemberAchievementController::class, 'index']);
        Route::get('/impact', [MemberImpactController::class, 'show']);

        Route::get('/preferences', [MemberPreferenceController::class, 'show']);
        Route::put('/preferences', [MemberPreferenceController::class, 'update']);
        Route::patch('/preferences', [MemberPreferenceController::class, 'update']);

        Route::get('/subscription', [MemberSubscriptionController::class, 'show']);
        Route::post('/subscription/change-plan', [MemberSubscriptionController::class, 'changePlan']);
        Route::post('/subscription/cancel', [MemberSubscriptionController::class, 'cancel']);
        Route::post('/subscription/resume', [MemberSubscriptionController::class, 'resume']);
        Route::post('/subscription/update-payment-method', [MemberSubscriptionController::class, 'updatePaymentMethod']);

        Route::post('/support/tickets', [MemberSupportController::class, 'storeTicket']);
        Route::post('/onboarding/complete', [MemberOnboardingController::class, 'complete']);

        Route::get('/reading-history', [MemberHistoryController::class, 'index']);
        Route::get('/explore', [MemberExploreController::class, 'index']);

        Route::post('/account/export', [MemberAccountController::class, 'export']);
        Route::get('/account/export', [MemberAccountController::class, 'export']);
        Route::get('/account/download', [MemberAccountController::class, 'download']);
        Route::patch('/account', [MemberAccountController::class, 'update']);
        Route::post('/account', [MemberAccountController::class, 'update']);
        Route::delete('/account', [MemberAccountController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'narlit.user.access'])->prefix('organization')->group(function () {
        Route::get('/dashboard', [OrganizationDashboardController::class, 'show']);
        Route::get('/articles', [OrganizationArticleController::class, 'index']);
        Route::post('/articles', [OrganizationArticleController::class, 'store']);
        Route::get('/payouts', [OrganizationPayoutController::class, 'index']);
        Route::post('/stripe-connect/onboarding', [OrganizationStripeConnectController::class, 'onboarding']);
        Route::get('/stripe-connect/status', [OrganizationStripeConnectController::class, 'status']);
    });

    Route::middleware(['auth:sanctum', 'narlit.admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);
        Route::get('/overview', [AdminDashboardController::class, 'overview']);
        Route::get('/analytics', [AdminAnalyticsController::class, 'combined']);
        Route::get('/audit', [AdminAuditLogController::class, 'index']);
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export']);
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);
        Route::get('/settings', [AdminPlatformSettingsController::class, 'index']);
        Route::get('/settings/{group}', [AdminPlatformSettingsController::class, 'show']);
        Route::put('/settings/{group}', [AdminPlatformSettingsController::class, 'update']);
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
        Route::get('/subscriptions/metrics', [AdminSubscriptionRevenueController::class, 'metrics']);
        Route::get('/subscriptions', [AdminSubscriptionRevenueController::class, 'index']);
        Route::get('/subscriptions/{publicId}', [AdminSubscriptionRevenueController::class, 'show']);
        Route::post('/subscriptions/{publicId}/cancel', [AdminSubscriptionRevenueController::class, 'cancel']);
        Route::post('/subscriptions/{publicId}/refund', [AdminSubscriptionRevenueController::class, 'refundSubscription']);
        Route::post('/payments/{publicId}/refund', [AdminSubscriptionRevenueController::class, 'refund']);
        Route::get('/payouts/summary', [AdminPayoutController::class, 'summary']);
        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::post('/payouts/generate', [AdminPayoutController::class, 'generate']);
        Route::get('/payouts/{publicId}', [AdminPayoutController::class, 'show']);
        Route::get('/payouts/{publicId}/items', [AdminPayoutController::class, 'items']);
        Route::post('/payouts/{publicId}/execute', [AdminPayoutController::class, 'execute']);
        Route::post('/payout-items/{id}/retry', [AdminPayoutController::class, 'retryItem']);
        Route::post('/payouts/items/{id}/retry', [AdminPayoutController::class, 'retryItem']);
        Route::post('/payouts/{publicId}/cancel', [AdminPayoutController::class, 'cancel']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{publicId}', [AdminUserController::class, 'show']);
        Route::post('/users/{publicId}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{publicId}/activate', [AdminUserController::class, 'activate']);
        Route::post('/users/{publicId}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->middleware('throttle:admin.users.password_reset');
        Route::post('/users/{publicId}/reset-mfa', [AdminUserController::class, 'resetMfa'])->middleware('throttle:admin.users.reset_mfa');
        Route::delete('/users/{publicId}/tokens', [AdminUserController::class, 'revokeTokens']);
        Route::get('/organizations', [OrganizationReviewController::class, 'index']);
        Route::post('/organizations/{publicId}/approve', [OrganizationReviewController::class, 'approve']);
        Route::post('/organizations/{publicId}/reject', [OrganizationReviewController::class, 'reject']);
    });
});
