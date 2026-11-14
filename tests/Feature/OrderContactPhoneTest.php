<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderContactPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_without_a_valid_saved_or_submitted_phone_creates_no_order_or_payment(): void
    {
        config(['shop.payment' => 'online', 'paymongo.mode' => 'simulated']);
        $customer = $this->customer(null);
        $product = $this->product();

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
            'order_type' => 'pickup', 'payment_method' => 'qrph',
            'checkout_request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_phone');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_updates_profile_and_snapshots_the_valid_order_contact(): void
    {
        $customer = $this->customer('09171112222');
        $product = $this->product();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
            'order_type' => 'pickup', 'payment_method' => 'cash',
            'checkout_request_id' => (string) Str::uuid(),
            'contact_phone' => '+639171234567',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.order.customer_phone', '09171234567');

        $orderId = $response->json('data.order.id');
        $this->assertSame('09171234567', $customer->fresh()->phone);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'customer_phone' => '09171234567']);
    }

    public function test_customer_registration_requires_a_valid_mobile_phone(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Customer', 'username' => 'phone_required_test',
            'email' => 'phone-required@example.test', 'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'phone-required@example.test']);
    }

    private function customer(?string $phone): User
    {
        return User::create([
            'name' => 'Phone Test Customer', 'username' => 'phone' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)) . '@example.test', 'password' => 'password',
            'phone' => $phone, 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Phone Test Category']);
        return Product::create([
            'category_id' => $category->id, 'name' => 'Phone Test Latte',
            'base_price' => 100, 'is_available' => true,
        ]);
    }
}
