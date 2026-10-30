<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StructuredAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_list_show_and_update_structured_address_without_losing_pin(): void
    {
        $customer = $this->customer();

        $created = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'label' => 'Home',
                'street' => '12 Maharlika Highway',
                'barangay' => 'Labit Proper',
                'city' => 'Urdaneta City',
                'province' => 'Pangasinan',
                'postal_code' => '2428',
                'latitude' => 15.950571,
                'longitude' => 120.538425,
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.barangay', 'Labit Proper')
            ->assertJsonPath('data.city', 'Urdaneta City')
            ->assertJsonPath('data.province', 'Pangasinan')
            ->assertJsonPath('data.postal_code', '2428')
            ->assertJsonPath('data.latitude', 15.950571)
            ->assertJsonPath('data.longitude', 120.538425);

        $id = $created->json('data.id');
        $this->assertDatabaseHas('addresses', [
            'id' => $id,
            'latitude' => 15.950571,
            'longitude' => 120.538425,
            'formatted_address' => '12 Maharlika Highway, Labit Proper, Urdaneta City, Pangasinan, 2428',
        ]);

        $this->getJson("/api/v1/addresses/{$id}")
            ->assertOk()
            ->assertJsonPath('data.street', '12 Maharlika Highway')
            ->assertJsonPath('data.latitude', 15.950571)
            ->assertJsonPath('data.longitude', 120.538425);

        $this->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.formatted_address', '12 Maharlika Highway, Labit Proper, Urdaneta City, Pangasinan, 2428');

        $this->putJson("/api/v1/addresses/{$id}", [
            'city' => 'Urdaneta',
        ])->assertOk()
            ->assertJsonPath('data.city', 'Urdaneta')
            ->assertJsonPath('data.latitude', 15.950571)
            ->assertJsonPath('data.longitude', 120.538425);

        $this->assertDatabaseHas('addresses', [
            'id' => $id,
            'latitude' => 15.950571,
            'longitude' => 120.538425,
            'city' => 'Urdaneta',
        ]);
    }

    public function test_address_coordinates_must_be_within_valid_ranges(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/addresses', [
                'full_address' => 'Urdaneta City, Pangasinan',
                'latitude' => 91,
                'longitude' => 181,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_checkout_using_saved_address_preserves_readable_address_and_exact_coordinates(): void
    {
        $customer = $this->customer();
        $address = Address::create([
            'user_id' => $customer->id,
            'label' => 'Home',
            'street' => '12 Maharlika Highway',
            'barangay' => 'Labit Proper',
            'city' => 'Urdaneta City',
            'province' => 'Pangasinan',
            'postal_code' => '2428',
            'formatted_address' => '12 Maharlika Highway, Labit Proper, Urdaneta City, Pangasinan, 2428',
            'full_address' => '12 Maharlika Highway, Labit Proper, Urdaneta City, Pangasinan, 2428',
            'latitude' => 15.950571,
            'longitude' => 120.538425,
            'is_default' => true,
        ]);
        $category = Category::create(['name' => 'Address Test']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Address test coffee',
            'base_price' => 100,
            'is_available' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'order_type' => 'delivery',
                'payment_method' => 'cod',
                'delivery_address' => $address->formatted_address,
                'delivery_lat' => $address->latitude,
                'delivery_lng' => $address->longitude,
            ])
            ->assertCreated()
            ->assertJsonPath('data.order.delivery_address', $address->formatted_address)
            ->assertJsonPath('data.order.delivery_lat', 15.950571)
            ->assertJsonPath('data.order.delivery_lng', 120.538425);
    }

    private function customer(): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => 'Address Test Customer',
            'email' => "{$suffix}@example.test",
            'username' => "customer{$suffix}",
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}
