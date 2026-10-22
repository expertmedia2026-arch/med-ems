<?php

use App\Http\Middleware\ApiResponseEnvelope;
use App\Http\Middleware\EnsureNotPatient;
use App\Http\Middleware\EnsureMainPharmacyAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'staff' => EnsureNotPatient::class,
            'main.pharmacy' => EnsureMainPharmacyAccess::class,
            'auth.session' => AuthenticateSession::class,

        ]);
        $middleware->api(append: [ApiResponseEnvelope::class]);
        // The payment gateway calls this URL itself, so it cannot carry a CSRF token
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $headers = [];

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'data' => null,
                    'errors' => $exception->errors(),
                ], $exception->status);
            }

            if ($exception instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated.';
            } elseif ($exception instanceof AuthorizationException) {
                $status = $exception->hasStatus() ? $exception->status() : 403;
                $message = $exception->getMessage() ?: 'This action is unauthorized.';
            } elseif ($exception instanceof ModelNotFoundException) {
                $status = 404;
                $message = 'Resource not found.';
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $headers = $exception->getHeaders();
                $message = $exception->getMessage() ?: (Response::$statusTexts[$status] ?? 'The request could not be completed.');
            } else {
                $status = 500;
                $message = 'The server encountered an error.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => null,
            ], $status, $headers);
        });
    })->create();
