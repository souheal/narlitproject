<?php

use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminArticleModerationController;
use App\Http\Controllers\Api\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AdminManagementController;
use App\Http\Controllers\Api\Admin\AdminPayoutController;
use App\Http\Controllers\Api\Admin\AdminPlatformSettingsController;
use App\Http\Controllers\Api\Admin\AdminSubscriptionRevenueController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\OrganizationReviewController;
use App\Http\Controllers\Api\Auth\AdminSessionRefreshController;
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
                    'roles' => $request->user()->safeAdminRoleNames(),
                    'permissions' => $request->user()->safeAdminPermissionNames(),
                ],
            ]);
        });

        Route::middleware('auth:sanctum')->post('/logout', [LoginController::class, 'destroy']);
        Route::middleware(['auth:sanctum', 'narlit.admin', 'throttle:admin.destructive'])
            ->post('/refresh', [AdminSessionRefreshController::class, 'store']);
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
        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview'])->middleware(['permission:analytics.view', 'throttle:admin.analytics'])->name('admin.dashboard');
        Route::get('/analytics/timeseries', [AdminAnalyticsController::class, 'timeseries'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/analytics/top-organizations', [AdminAnalyticsController::class, 'topOrganizations'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/analytics/top-articles', [AdminAnalyticsController::class, 'topArticles'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/analytics/categories', [AdminAnalyticsController::class, 'categories'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/analytics/funnel', [AdminAnalyticsController::class, 'funnel'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/analytics/cohorts', [AdminAnalyticsController::class, 'cohorts'])->middleware(['permission:analytics.view', 'throttle:admin.analytics']);
        Route::get('/audit/export', [AdminAuditLogController::class, 'export'])->middleware(['permission:audit.export', 'throttle:admin.audit.export'])->name('admin.audit.export');
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->middleware(['permission:audit.view', 'throttle:admin.read'])->name('admin.audit.index');
        Route::get('/audit/{id}', [AdminAuditLogController::class, 'show'])->middleware(['permission:audit.view', 'throttle:admin.read'])->name('admin.audit.show');
        Route::get('/settings', [AdminPlatformSettingsController::class, 'index'])->middleware(['permission:settings.view', 'throttle:admin.read']);
        Route::get('/settings/{group}', [AdminPlatformSettingsController::class, 'show'])->middleware(['permission:settings.view', 'throttle:admin.read']);
        Route::put('/settings/{group}', [AdminPlatformSettingsController::class, 'update'])->middleware(['permission:settings.update', 'throttle:admin.settings.update']);
        Route::get('/articles', [AdminArticleModerationController::class, 'index'])->middleware(['permission:articles.view', 'throttle:admin.read']);
        Route::get('/articles/{publicId}', [AdminArticleModerationController::class, 'show'])->middleware(['permission:articles.view', 'throttle:admin.read']);
        Route::post('/articles/{publicId}/approve', [AdminArticleModerationController::class, 'approve'])->middleware(['permission:articles.approve', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/reject', [AdminArticleModerationController::class, 'reject'])->middleware(['permission:articles.reject', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/request-changes', [AdminArticleModerationController::class, 'requestChanges'])->middleware(['permission:articles.request_changes', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/publish', [AdminArticleModerationController::class, 'publish'])->middleware(['permission:articles.approve', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/feature', [AdminArticleModerationController::class, 'feature'])->middleware(['permission:articles.feature', 'throttle:admin.destructive']);
        Route::delete('/articles/{publicId}/feature', [AdminArticleModerationController::class, 'unfeature'])->middleware(['permission:articles.feature', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/archive', [AdminArticleModerationController::class, 'archive'])->middleware(['permission:articles.archive', 'throttle:admin.destructive']);
        Route::post('/articles/{publicId}/restore', [AdminArticleModerationController::class, 'restore'])->middleware(['permission:articles.restore', 'throttle:admin.destructive']);
        Route::get('/subscriptions/summary', [AdminSubscriptionRevenueController::class, 'summary'])->middleware(['permission:subscriptions.view', 'throttle:admin.read']);
        Route::get('/subscriptions', [AdminSubscriptionRevenueController::class, 'index'])->middleware(['permission:subscriptions.view', 'throttle:admin.read']);
        Route::get('/subscriptions/{publicId}', [AdminSubscriptionRevenueController::class, 'show'])->middleware(['permission:subscriptions.view', 'throttle:admin.read']);
        Route::post('/subscriptions/{publicId}/cancel', [AdminSubscriptionRevenueController::class, 'cancel'])->middleware(['permission:subscriptions.cancel', 'throttle:admin.destructive', 'idempotency:admin.subscription.cancel']);
        Route::post('/payments/{publicId}/refund', [AdminSubscriptionRevenueController::class, 'refund'])->middleware(['permission:payments.refund', 'throttle:admin.destructive', 'idempotency:admin.payment.refund']);
        Route::get('/payouts/summary', [AdminPayoutController::class, 'summary'])->middleware(['permission:payouts.view', 'throttle:admin.read']);
        Route::get('/payouts', [AdminPayoutController::class, 'index'])->middleware(['permission:payouts.view', 'throttle:admin.read']);
        Route::post('/payouts/generate', [AdminPayoutController::class, 'generate'])->middleware(['permission:payouts.generate', 'throttle:admin.destructive', 'idempotency:admin.payout.generate']);
        Route::get('/payouts/{publicId}', [AdminPayoutController::class, 'show'])->middleware(['permission:payouts.view', 'throttle:admin.read']);
        Route::post('/payouts/{publicId}/execute', [AdminPayoutController::class, 'execute'])->middleware(['permission:payouts.execute', 'throttle:admin.destructive', 'idempotency:admin.payout.execute']);
        Route::post('/payouts/items/{id}/retry', [AdminPayoutController::class, 'retryItem'])->middleware(['permission:payouts.retry', 'throttle:admin.destructive'])->name('admin.payout-items.retry');
        Route::post('/payouts/{publicId}/cancel', [AdminPayoutController::class, 'cancel'])->middleware(['permission:payouts.cancel', 'throttle:admin.destructive']);
        Route::get('/users', [AdminUserController::class, 'index'])->middleware(['permission:users.view', 'throttle:admin.read']);
        Route::get('/users/{publicId}', [AdminUserController::class, 'show'])->middleware(['permission:users.view', 'throttle:admin.read']);
        Route::patch('/users/{publicId}/status', [AdminUserController::class, 'updateStatus'])->middleware(['permission:users.suspend|users.activate', 'throttle:admin.destructive']);
        Route::post('/users/{publicId}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->middleware(['permission:users.reset_password', 'throttle:admin.destructive']);
        Route::post('/users/{publicId}/reset-mfa', [AdminUserController::class, 'resetMfa'])->middleware(['permission:users.reset_mfa', 'throttle:admin.destructive']);
        Route::delete('/users/{publicId}/tokens', [AdminUserController::class, 'revokeTokens'])->middleware(['permission:users.revoke_tokens', 'throttle:admin.destructive']);
        Route::get('/organizations', [OrganizationReviewController::class, 'index'])->middleware(['permission:organizations.view', 'throttle:admin.read']);
        Route::post('/organizations/{publicId}/approve', [OrganizationReviewController::class, 'approve'])->middleware(['permission:organizations.approve', 'throttle:admin.destructive']);
        Route::post('/organizations/{publicId}/reject', [OrganizationReviewController::class, 'reject'])->middleware(['permission:organizations.reject', 'throttle:admin.destructive']);
        Route::get('/admins', [AdminManagementController::class, 'index'])->middleware(['permission:admins.view', 'throttle:admin.read']);
        Route::get('/admins/{publicId}', [AdminManagementController::class, 'show'])->middleware(['permission:admins.view', 'throttle:admin.read']);
        Route::put('/admins/{publicId}/roles', [AdminManagementController::class, 'updateRoles'])->middleware(['permission:admins.manage_roles', 'throttle:admin.destructive']);
    });
});
