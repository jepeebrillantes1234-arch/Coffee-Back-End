<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ReverseGeocodeController extends Controller
{
    use Responds;

    public function __invoke(Request $request)
    {
        $coordinates = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $key = config('services.google.maps_geocoding_key');
        if (! is_string($key) || trim($key) === '') {
            return $this->fail('Location lookup is not configured.', 503);
        }

        try {
            $response = Http::timeout(8)->get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                [
                    'latlng' => $coordinates['latitude'].','.$coordinates['longitude'],
                    'language' => 'en',
                    'key' => $key,
                ],
            );
            if (! $response->successful()) {
                return $this->fail('Could not look up this location right now.', 502);
            }

            $google = $response->json();
            if (! is_array($google) || ($google['status'] ?? null) !== 'OK') {
                return $this->fail('Could not find a readable address for this pin.', 422);
            }

            $results = $google['results'] ?? [];
            // Google may return a road or plus-code first. Prefer the result
            // with the most address components so barangay/city/province are
            // retained when Google has them for the selected coordinates.
            usort($results, static function ($left, $right) {
                return count($right['address_components'] ?? []) <=> count($left['address_components'] ?? []);
            });
            $result = $results[0] ?? null;
            if (! is_array($result)) {
                return $this->fail('Could not find a readable address for this pin.', 422);
            }

            $formattedAddress = trim((string) ($result['formatted_address'] ?? ''));
            $components = $result['address_components'] ?? [];
            $priorities = [
                'administrative_area_level_5',
                'administrative_area_level_4',
                'sublocality_level_1',
                'sublocality_level_2',
                'sublocality',
                'neighborhood',
                'administrative_area_level_3',
                'locality',
            ];
            $name = '';
            foreach ($priorities as $type) {
                foreach ($components as $component) {
                    if (in_array($type, $component['types'] ?? [], true)) {
                        $name = trim((string) ($component['long_name'] ?? ''));
                        if ($name !== '') {
                            break 2;
                        }
                    }
                }
            }
            if ($name === '') {
                $name = trim((string) (explode(',', $formattedAddress)[0] ?? ''));
            }

            $findComponent = static function (array $types) use ($components): string {
                foreach ($types as $type) {
                    foreach ($components as $component) {
                        if (in_array($type, $component['types'] ?? [], true)) {
                            $value = trim((string) ($component['long_name'] ?? ''));
                            if ($value !== '') return $value;
                        }
                    }
                }
                return '';
            };
            $barangay = $findComponent([
                'administrative_area_level_5',
                'administrative_area_level_4',
                'sublocality_level_1',
                'sublocality',
                'neighborhood',
            ]);
            $city = $findComponent([
                'locality',
                'administrative_area_level_3',
                'administrative_area_level_2',
            ]);
            $province = $findComponent(['administrative_area_level_1']);
            $country = $findComponent(['country']);
            $parts = array_values(array_unique(array_filter([
                $barangay,
                $city,
                $province,
                $country,
            ], static fn ($part) => $part !== '')));
            $fullAddress = implode(', ', $parts);
            if ($fullAddress === '') $fullAddress = $formattedAddress;

            return $this->ok([
                'name' => $name,
                'address' => $fullAddress,
                'formatted_address' => $formattedAddress,
                'barangay' => $barangay,
                'city' => $city,
                'province' => $province,
                'country' => $country,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return $this->fail('Location lookup timed out. You can enter the address manually.', 502);
        }
    }
}
