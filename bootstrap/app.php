<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ngrok terminates HTTPS and forwards the original scheme to Laravel.
        // Trust only the forwarded protocol header so generated forms and URLs
        // stay on HTTPS without trusting a client-supplied forwarded host.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\LogSlowApiRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isLostDatabaseConnection = static function (\Throwable $exception): bool {
            $detector = new LostConnectionDetector();

            do {
                if ($exception instanceof LostConnectionException) {
                    return true;
                }

                $candidate = $exception instanceof QueryException
                    ? $exception->getPrevious()
                    : $exception;

                if ($candidate instanceof \PDOException && $detector->causedByLostConnection($candidate)) {
                    return true;
                }

                $exception = $exception->getPrevious();
            } while ($exception !== null);

            return false;
        };

        $errorPageData = static function (Request $request): array {
            $user = $request->user();
            $role = $user && $user->status === 'active' ? $user->role : null;

            return [
                'role' => $role,
                'homeUrl' => match ($role) {
                    'admin' => route('admin.dashboard'),
                    'staff' => route('staff.home'),
                    default => route('login'),
                },
                'supportUrl' => match ($role) {
                    'admin' => route('admin.support.index'),
                    'staff' => route('staff.support'),
                    default => null,
                },
            ];
        };

        // Keep one stable JSON envelope for every API exception. Laravel still
        // performs exception reporting through its normal reporter, while
        // clients receive safe messages instead of debug traces or SQL details.
        $exceptions->render(function (\Throwable $exception, Request $request) use ($isLostDatabaseConnection) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $isLostDatabaseConnection($exception) => 503,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            $errors = $exception instanceof ValidationException
                ? $exception->errors()
                : null;
            $message = match ($status) {
                400 => 'The request could not be understood. Please check the information and try again.',
                401 => 'Your session has expired. Please sign in again.',
                403 => 'You do not have permission to perform this action.',
                404 => 'The requested item could not be found.',
                405 => 'This action is not supported.',
                408, 504 => 'The request timed out. Please check the result before submitting again.',
                409 => 'This request conflicts with the latest information. Refresh and review the current status.',
                419 => 'This page has expired. Please refresh it and try again.',
                429 => 'Too many requests. Please wait a moment and try again.',
                500 => 'Something went wrong on our side. Please try again later.',
                502 => 'The service is temporarily unavailable. Please try again shortly.',
                503 => 'KJM Coffee Shop is temporarily unavailable. Please try again shortly.',
                422 => 'Some information needs your attention. Please review the submitted fields.',
                default => $exception instanceof ValidationException
                    ? $exception->getMessage()
                    : ($exception instanceof HttpExceptionInterface && $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'The request could not be completed. Please check it and try again.'),
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $errors,
            ], $status, $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($errorPageData) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            if (! in_array($status, [400, 401, 403, 404, 405, 408, 409, 419, 422, 429, 500, 502, 503, 504], true)) {
                return null;
            }

            return response()->view(
                "errors.{$status}",
                ['exception' => $exception] + $errorPageData($request),
                $status,
                $exception->getHeaders(),
            );
        });

        // Do not expose exception details in browser HTML, even with APP_DEBUG
        // enabled. Keep API and explicit HTTP exception handling unchanged.
        $exceptions->render(function (\Throwable $exception, Request $request) use ($errorPageData, $isLostDatabaseConnection) {
            if (
                $request->expectsJson()
                || $request->is('api/*')
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException
            ) {
                return null;
            }

            $status = $isLostDatabaseConnection($exception) ? 503 : 500;
            return response()->view("errors.{$status}", $errorPageData($request), $status);
        });
    })->create();
