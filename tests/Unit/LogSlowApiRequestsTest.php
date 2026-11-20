<?php

namespace Tests\Unit;

use App\Http\Middleware\LogSlowApiRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LogSlowApiRequestsTest extends TestCase
{
    public function test_slow_api_log_contains_route_timing_and_status_but_no_query_values(): void
    {
        Config::set('services.api.slow_request_threshold_ms', 1);
        Log::shouldReceive('warning')->once()->with(
            'Slow API request.',
            Mockery::on(function (array $context): bool {
                return $context['method'] === 'GET'
                    && $context['route'] === 'unmatched'
                    && $context['status'] === 200
                    && $context['duration_ms'] >= 1
                    && !array_key_exists('token', $context);
            }),
        );

        $request = Request::create('/api/v1/test?token=do-not-log', 'GET');
        $response = app(LogSlowApiRequests::class)->handle($request, function () {
            usleep(5000);

            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
    }
}
