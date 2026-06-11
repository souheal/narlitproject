<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureNarLitUserAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        $middleware->alias([
            'narlit.user.access' => EnsureNarLitUserAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $throwable): bool => $request->is('api/*') || $request->expectsJson()
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
            ], 429);
        });
    })->create();
