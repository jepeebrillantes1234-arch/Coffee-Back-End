<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class LogSlowApiRequests
{
    public function handle(Request $request, Closure $next)
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logIfSlow($request, $startedAt, null, $exception);
            throw $exception;
        }

        $this->logIfSlow($request, $startedAt, $response->getStatusCode());

        return $response;
    }

    private function logIfSlow(Request $request, int $startedAt, ?int $status, ?Throwable $exception = null): void
    {
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $thresholdMs = max(0, (int) config('services.api.slow_request_threshold_ms', 2000));

        if ($thresholdMs === 0 || $durationMs < $thresholdMs) {
            return;
        }

        $route = $request->route();
        $context = [
            'method' => $request->method(),
            'route' => $route?->getName() ?: ($route?->uri() ?? 'unmatched'),
            'status' => $status,
            'duration_ms' => (int) round($durationMs),
        ];

        if ($exception !== null) {
            $context['exception_type'] = $exception::class;
        }

        Log::warning('Slow API request.', $context);
    }
}
