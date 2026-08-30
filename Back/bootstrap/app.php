<?php

use App\Console\Commands\CleanupIdempotencyKeys;
use App\Console\Commands\CleanupExpiredSanctumTokens;
use App\Console\Commands\CreateSuperAdmin;
use App\Console\Commands\ImportIrsExemptOrganizations;
use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureNarLitUserAccess;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UseAdminTokenCookieForSanctum;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CleanupExpiredSanctumTokens::class,
        CleanupIdempotencyKeys::class,
        CreateSuperAdmin::class,
        ImportIrsExemptOrganizations::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            SecurityHeaders::class,
        ]);
        $middleware->statefulApi();
        $middleware->api(prepend: [
            UseAdminTokenCookieForSanctum::class,
        ]);
        $middleware->throttleApi('api');
        $middleware->alias([
            'narlit.admin' => EnsureAdminAccess::class,
            'narlit.user.access' => EnsureNarLitUserAccess::class,
            'idempotency' => EnsureIdempotency::class,
            'permission' => EnsurePermission::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $throwable): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ApiException $exception, Request $request) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status());
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            $errors = $exception->errors();
            $firstMessage = collect($errors)->flatten()->first() ?? 'Please check the submitted information.';

            return response()->json([
                'message' => $firstMessage,
                'errors' => $errors,
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return response()->json([
                'message' => 'Authentication is required.',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'errors' => [],
            ], 429)->withHeaders($exception->getHeaders());
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'errors' => [],
            ], 403);
        });

        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            return SecurityHeaders::apply($response, $request);
        });
    })->create();
