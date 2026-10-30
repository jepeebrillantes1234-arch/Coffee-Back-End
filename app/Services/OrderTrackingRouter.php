<?php

namespace App\Services;

use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OrderTrackingRouter
{
    public function route(int $orderId, ?array $origin, array $destination): array
    {
        if (config('services.routing.provider') !== 'osrm') {
            return ['status' => 'unavailable', 'error' => 'Road routing is not configured.', 'points' => []];
        }
        if (!$origin || ($destination['lat'] ?? null) === null || ($destination['lng'] ?? null) === null) {
            return ['status' => 'unavailable', 'error' => 'A route is not available until both locations are known.', 'points' => []];
        }

        $key = 'tracking.osrm.order.' . $orderId;
        $last = Cache::get($key . '.route');
        $attempt = (int) Cache::get($key . '.last_attempt', 0);
        $now = now()->timestamp;
        $movedKm = isset($last['origin'])
            ? Geo::km((float) $last['origin']['lat'], (float) $last['origin']['lng'], (float) $origin['lat'], (float) $origin['lng'])
            : INF;
        if ($last && $now - (int) $last['fetched_at'] < 45 && $movedKm < 0.05) {
            return $last + ['status' => 'available', 'error' => null];
        }
        if ($now - $attempt < 20) {
            return $last
                ? $last + ['status' => 'stale', 'error' => 'Refreshing the road route shortly. Showing the last available route.']
                : ['status' => 'loading', 'error' => null, 'points' => []];
        }

        Cache::put($key . '.last_attempt', $now, now()->addMinutes(10));
        try {
            $base = rtrim((string) config('services.routing.osrm_base_url'), ' /');
            $url = $base . '/route/v1/driving/' . (float) $origin['lng'] . ',' . (float) $origin['lat'] . ';' . (float) $destination['lng'] . ',' . (float) $destination['lat'];
            $response = Http::acceptJson()->withUserAgent('KJMCoffeeShop/1.0 (order tracking)')->timeout(5)->get($url, [
                'overview' => 'full', 'geometries' => 'geojson', 'steps' => 'false',
            ]);
            $body = $response->json();
            $coordinates = $body['routes'][0]['geometry']['coordinates'] ?? [];
            if (!$response->successful() || ($body['code'] ?? null) !== 'Ok' || count($coordinates) < 2) {
                throw new \RuntimeException('OSRM returned no usable route.');
            }
            $route = [
                'points' => array_map(fn ($point) => ['lat' => (float) $point[1], 'lng' => (float) $point[0]], $coordinates),
                'distance_meters' => (int) round((float) $body['routes'][0]['distance']),
                'duration_seconds' => (int) round((float) $body['routes'][0]['duration']),
                'origin' => $origin,
                'fetched_at' => $now,
            ];
            Cache::put($key . '.route', $route, now()->addHours(6));
            return $route + ['status' => 'available', 'error' => null];
        } catch (\Throwable $exception) {
            logger()->warning('OSRM tracking route unavailable.', [
                'order_id' => $orderId,
                'exception' => class_basename($exception),
            ]);
            return $last
                ? $last + ['status' => 'stale', 'error' => 'Road routing is temporarily unavailable. Showing the last available route.']
                : ['status' => 'unavailable', 'error' => 'Road routing is temporarily unavailable.', 'points' => []];
        }
    }
}