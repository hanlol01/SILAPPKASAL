<?php

use App\Http\Middleware\AuditSensitiveAuthorizationDenials;
use App\Http\Middleware\GenerateRequestId;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\PrivateNoStore;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetApiLocale;
use App\Support\ApiErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', [
            GenerateRequestId::class,
            SetApiLocale::class,
            AuditSensitiveAuthorizationDenials::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'private.no-store' => PrivateNoStore::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.errors.validation_failed'),
                'error_code' => ApiErrorCode::ValidationFailed,
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.errors.unauthenticated'),
                'error_code' => ApiErrorCode::Unauthenticated,
                'errors' => null,
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.errors.forbidden'),
                'error_code' => ApiErrorCode::Forbidden,
                'errors' => null,
            ], 403);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('api.errors.too_many_requests'),
                'error_code' => ApiErrorCode::TooManyRequests,
                'errors' => null,
                'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 0),
            ], 429);
        });

        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            if ($request->is('api/v1/content*')) {
                $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
            }

            return $response;
        });
    })->create();
