<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($errorPageData) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
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
        $exceptions->render(function (\Throwable $exception, Request $request) use ($errorPageData) {
            if (
                $request->expectsJson()
                || $request->is('api/*')
                || $exception instanceof HttpExceptionInterface
            ) {
                return null;
            }

            return response()->view('errors.500', $errorPageData($request), 500);
        });
    })->create();
