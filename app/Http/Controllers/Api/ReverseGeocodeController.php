<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReverseGeocodeController extends Controller
{
    use Responds;

    private const CACHE_PREFIX = 'nominatim:reverse:v3:';
    private const LAST_REQUEST_KEY = 'nominatim:reverse:last-request';

    public function __invoke(Request $request)
    {
        $coordinates = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $latitude = (float) $coordinates['latitude'];
        $longitude = (float) $coordinates['longitude'];
        $coordinateKey = sprintf('%.7f|%.7f', $latitude, $longitude);
        $cacheKey = self::CACHE_PREFIX.hash('sha256', $coordinateKey);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (($cached['_no_result'] ?? false) === true) {
                return $this->fail('Could not find a readable address for this pin.', 422);
            }

            return $this->ok($cached);
        }

        // Cache::add is atomic for the configured shared database cache store.
        // Decline early requests instead of queueing or retrying them.
        if (! Cache::add(self::LAST_REQUEST_KEY, true, now()->addSecond())) {
            return $this->fail('Location lookup is busy. Please try again shortly.', 429);
        }

        $appUrl = trim((string) config('app.url'));
        $userAgent = 'KJM Coffee Shop/1.0';
        if (filter_var($appUrl, FILTER_VALIDATE_URL) !== false) {
            $userAgent .= ' (+'.rtrim($appUrl, '/').')';
        }

        try {
            $response = Http::withUserAgent($userAgent)
                ->acceptJson()
                ->withHeaders(['Accept-Language' => 'en'])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                ]);

            if ($response->status() === 404) {
                Cache::put($cacheKey, ['_no_result' => true], now()->addMinutes(5));

                return $this->fail('Could not find a readable address for this pin.', 422);
            }

            if (! $response->successful()) {
                logger()->warning('Nominatim reverse geocoding returned a provider error.', [
                    'provider_status' => $response->status(),
                ]);

                return $this->fail('Location lookup is temporarily unavailable. Please try again.', 502);
            }

            $result = $response->json();
            $address = is_array($result) && is_array($result['address'] ?? null)
                ? $result['address']
                : [];

            $pick = static function (array $keys) use ($address): string {
                foreach ($keys as $key) {
                    $value = trim((string) ($address[$key] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }

                return '';
            };

            $houseNumber = $pick(['house_number']);
            $road = $pick(['road']);
            $street = trim(implode(' ', array_filter([$houseNumber, $road])));
            // Only pass through Nominatim's explicit barangay component. Do not
            // infer it from neighbourhood, suburb, or zone fields.
            $barangay = $pick(['barangay']);
            $city = $pick(['city', 'town', 'village', 'municipality']);
            $province = $pick(['province', 'state']);
            $postalCode = $pick(['postcode']);
            $country = $pick(['country']);
            $displayName = is_array($result)
                ? trim((string) ($result['display_name'] ?? ''))
                : '';

            $fallbackParts = array_values(array_unique(array_filter([
                $street,
                $barangay,
                $city,
                $province,
                $postalCode,
                $country,
            ], static fn ($part) => $part !== '')));
            $formattedAddress = $displayName !== ''
                ? $displayName
                : implode(', ', $fallbackParts);

            if ($formattedAddress === '' && $barangay === '' && $city === ''
                && $province === '' && $street === '' && $country === '') {
                Cache::put($cacheKey, ['_no_result' => true], now()->addMinutes(5));

                return $this->fail('Could not find a readable address for this pin.', 422);
            }

            $compactParts = array_values(array_unique(array_filter([
                $barangay,
                $city,
                $province,
                $country,
            ], static fn ($part) => $part !== '')));
            $compactAddress = implode(', ', $compactParts);
            if ($compactAddress === '') {
                $compactAddress = $formattedAddress;
            }

            $name = trim((string) ($result['name'] ?? ''));
            if ($name === '') {
                $name = $barangay ?: ($city ?: ($province ?: ($street ?: $formattedAddress)));
            }

            // Keep the existing success JSON field names for Flutter callers.
            $place = [
                'name' => $name,
                'address' => $compactAddress,
                'formatted_address' => $formattedAddress,
                'street' => $street,
                'barangay' => $barangay,
                'city' => $city,
                'province' => $province,
                'postal_code' => $postalCode,
                'country' => $country,
            ];
            Cache::put($cacheKey, $place, now()->addDay());

            return $this->ok($place);
        } catch (\Throwable $exception) {
            // Log only the exception class; do not log request URLs or payloads.
            logger()->warning('Nominatim reverse geocoding request failed.', [
                'exception_type' => get_class($exception),
            ]);

            return $this->fail('Location lookup timed out. You can enter the address manually.', 502);
        }
    }
}
