<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_nominatim_success_keeps_the_existing_response_fields_and_request_contract(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'name' => 'Palina West Zone 3',
                'display_name' => '12 Maharlika Highway, Palina West Zone 3, Urdaneta City, Pangasinan 2428, Philippines',
                'address' => [
                    'house_number' => '12',
                    'road' => 'Maharlika Highway',
                    'neighbourhood' => 'Palina West Zone 3',
                    'suburb' => 'Palina East Zone 3',
                    'barangay' => 'Zone 3',
                    'zone' => 'Zone 3',
                    'city' => 'Urdaneta City',
                    'state' => 'Pangasinan',
                    'postcode' => '2428',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $response = $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=15.950571&longitude=120.538425')
            ->assertOk()
            ->assertJsonPath('data.name', 'Palina West Zone 3')
            ->assertJsonPath('data.address', 'Zone 3, Urdaneta City, Pangasinan, Philippines')
            ->assertJsonPath('data.formatted_address', '12 Maharlika Highway, Palina West Zone 3, Urdaneta City, Pangasinan 2428, Philippines')
            ->assertJsonPath('data.street', '12 Maharlika Highway')
            ->assertJsonPath('data.barangay', 'Zone 3')
            ->assertJsonPath('data.city', 'Urdaneta City')
            ->assertJsonPath('data.province', 'Pangasinan')
            ->assertJsonPath('data.postal_code', '2428')
            ->assertJsonPath('data.country', 'Philippines');

        $this->assertSame([
            'name',
            'address',
            'formatted_address',
            'street',
            'barangay',
            'city',
            'province',
            'postal_code',
            'country',
        ], array_keys($response->json('data')));

        Http::assertSent(function (ClientRequest $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $userAgent = implode(' ', $request->header('User-Agent'));

            return $request->method() === 'GET'
                && parse_url($request->url(), PHP_URL_PATH) === '/reverse'
                && ($query['lat'] ?? null) === '15.950571'
                && ($query['lon'] ?? null) === '120.538425'
                && ($query['format'] ?? null) === 'jsonv2'
                && ($query['addressdetails'] ?? null) === '1'
                && str_contains($userAgent, 'KJM Coffee Shop')
                && str_contains($userAgent, (string) config('app.url'));
        });

        // Identical coordinates are served from cache without another request.
        $this->getJson('/api/v1/location/reverse-geocode?latitude=15.950571&longitude=120.538425')
            ->assertOk();
        Http::assertSentCount(1);
    }

    public function test_customer_can_correct_a_misleading_nominatim_area_and_save_their_barangay(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'name' => 'Palina West Zone 3',
                'display_name' => 'Labit Proper area, Urdaneta City, Pangasinan, Philippines',
                'address' => [
                    'neighbourhood' => 'Palina West Zone 3',
                    'suburb' => 'Palina East Zone 3',
                    'zone' => 'Zone 3',
                    'barangay' => 'Zone 3',
                    'city' => 'Urdaneta City',
                    'state' => 'Pangasinan',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $customer = $this->customer();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=15.950571&longitude=120.538425')
            ->assertOk()
            ->assertJsonPath('data.barangay', 'Zone 3')
            ->assertJsonPath('data.formatted_address', 'Labit Proper area, Urdaneta City, Pangasinan, Philippines');

        $saved = $this->postJson('/api/v1/addresses', [
            'label' => 'Home',
            'street' => 'Zone 4',
            'barangay' => 'Labit Proper',
            'city' => 'Urdaneta City',
            'province' => 'Pangasinan',
            'formatted_address' => 'Zone 4, Labit Proper, Urdaneta City, Pangasinan',
            'latitude' => 15.950571,
            'longitude' => 120.538425,
        ])->assertCreated()
            ->assertJsonPath('data.barangay', 'Labit Proper')
            ->assertJsonPath('data.latitude', 15.950571)
            ->assertJsonPath('data.longitude', 120.538425);

        $this->assertDatabaseHas('addresses', [
            'id' => $saved->json('data.id'),
            'barangay' => 'Labit Proper',
            'latitude' => 15.950571,
            'longitude' => 120.538425,
        ]);
    }

    public function test_new_cache_version_does_not_serve_a_stale_barangay_result(): void
    {
        $coordinates = sprintf('%.7f|%.7f', 15.950571, 120.538425);
        $oldCacheKey = 'nominatim:reverse:v2:'.hash('sha256', $coordinates);
        Cache::put($oldCacheKey, [
            'name' => 'Palina West Zone 3',
            'address' => 'Palina West Zone 3, Urdaneta City',
            'formatted_address' => 'Palina West Zone 3, Urdaneta City, Philippines',
            'street' => '',
            'barangay' => 'Palina West Zone 3',
            'city' => 'Urdaneta City',
            'province' => '',
            'postal_code' => '',
            'country' => 'Philippines',
        ], now()->addDay());

        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Labit Proper, Urdaneta City, Pangasinan, Philippines',
                'address' => [
                    'neighbourhood' => 'Palina West Zone 3',
                    'suburb' => 'Palina East Zone 3',
                    'zone' => 'Zone 3',
                    'barangay' => 'Labit Proper',
                    'city' => 'Urdaneta City',
                    'state' => 'Pangasinan',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=15.950571&longitude=120.538425')
            ->assertOk()
            ->assertJsonPath('data.barangay', 'Labit Proper')
            ->assertJsonPath('data.formatted_address', 'Labit Proper, Urdaneta City, Pangasinan, Philippines');

        Http::assertSentCount(1);
    }

    public function test_incomplete_nominatim_address_returns_only_available_components(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Maharlika Highway, Philippines',
                'address' => [
                    'road' => 'Maharlika Highway',
                    'country' => 'Philippines',
                ],
            ]),
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=16.6886&longitude=121.5487')
            ->assertOk()
            ->assertJsonPath('data.name', 'Maharlika Highway')
            ->assertJsonPath('data.street', 'Maharlika Highway')
            ->assertJsonPath('data.barangay', '')
            ->assertJsonPath('data.city', '')
            ->assertJsonPath('data.province', '')
            ->assertJsonPath('data.postal_code', '')
            ->assertJsonPath('data.country', 'Philippines');
    }

    public function test_nominatim_no_result_is_cached_and_invalid_coordinates_do_not_call_provider(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'error' => 'Unable to geocode',
            ], 404),
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=16.6886&longitude=121.5487')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not find a readable address for this pin.');

        $this->getJson('/api/v1/location/reverse-geocode?latitude=16.6886&longitude=121.5487')
            ->assertUnprocessable();
        Http::assertSentCount(1);

        $this->getJson('/api/v1/location/reverse-geocode?latitude=91&longitude=181')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
        Http::assertSentCount(1);
    }

    public function test_uncached_requests_inside_the_one_second_window_are_rejected_without_provider_call(): void
    {
        Cache::put('nominatim:reverse:last-request', true, now()->addSeconds(5));
        Http::fake();

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/location/reverse-geocode?latitude=15.95&longitude=120.53')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Location lookup is busy. Please try again shortly.');

        Http::assertNothingSent();
    }

    private function customer(): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => 'Geocoding Customer',
            'username' => 'geo'.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}
