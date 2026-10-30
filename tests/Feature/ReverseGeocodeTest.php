<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_latitude_and_longitude_are_forwarded_to_google_and_return_readable_address(): void
    {
        config(['services.google.maps_geocoding_key' => 'test-geocoding-key']);
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Maharlika Highway, Santiago City, Isabela, Philippines',
                    'address_components' => [
                        ['long_name' => '12', 'types' => ['street_number']],
                        ['long_name' => 'Maharlika Highway', 'types' => ['route']],
                        ['long_name' => 'Labit Proper', 'types' => ['administrative_area_level_5']],
                        ['long_name' => 'Santiago City', 'types' => ['locality']],
                        ['long_name' => 'Isabela', 'types' => ['administrative_area_level_1']],
                        ['long_name' => '3311', 'types' => ['postal_code']],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=16.6886&longitude=121.5487')
            ->assertOk()
            ->assertJsonPath('data.formatted_address', 'Maharlika Highway, Santiago City, Isabela, Philippines')
            ->assertJsonPath('data.street', '12 Maharlika Highway')
            ->assertJsonPath('data.barangay', 'Labit Proper')
            ->assertJsonPath('data.city', 'Santiago City')
            ->assertJsonPath('data.province', 'Isabela')
            ->assertJsonPath('data.postal_code', '3311');

        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            'latlng=16.6886%2C121.5487',
        ));
    }

    public function test_out_of_range_coordinates_fail_validation_without_calling_google(): void
    {
        config(['services.google.maps_geocoding_key' => 'test-geocoding-key']);
        Http::fake();

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=91&longitude=181')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        Http::assertNothingSent();
    }

    public function test_google_provider_errors_are_reported_as_service_unavailable_not_invalid_coordinates(): void
    {
        config(['services.google.maps_geocoding_key' => 'test-geocoding-key']);
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'The test key is not authorized.',
            ]),
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=16.6886&longitude=121.5487')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Location lookup is temporarily unavailable. Please try again.');
    }

    private function customer(): User
    {
        $suffix = Str::lower(Str::random(10));
        return User::create([
            'name' => 'Geocoding Customer',
            'username' => 'geo' . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}