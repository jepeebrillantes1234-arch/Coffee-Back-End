<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use App\Support\ShopOperatingHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_shop_hours_use_philippine_time_and_boundaries(): void
    {
        Settings::set('shop_opening_time', '06:00');
        Settings::set('shop_closing_time', '20:00');

        $this->assertTrue(ShopOperatingHours::isOpen(CarbonImmutable::parse('2026-11-02 06:00', 'Asia/Manila')));
        $this->assertFalse(ShopOperatingHours::isOpen(CarbonImmutable::parse('2026-11-02 20:00', 'Asia/Manila')));
        $this->assertSame('Asia/Manila', ShopOperatingHours::details()['timezone']);
    }

    public function test_ordering_remains_available_until_admin_configures_shop_hours(): void
    {
        $details = ShopOperatingHours::details(CarbonImmutable::parse('2026-11-02 23:30', 'Asia/Manila'));
        $this->assertFalse($details['is_configured']);
        $this->assertTrue($details['is_open']);
    }

    public function test_app_config_returns_operating_hours_and_status(): void
    {
        Settings::set('shop_opening_time', '06:00');
        Settings::set('shop_closing_time', '20:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila'));
        $customer = User::create(['name' => 'Customer', 'email' => 'hours@example.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
        $this->actingAs($customer, 'sanctum');

        $this->getJson('/api/v1/app-config')->assertOk()
            ->assertJsonPath('data.settings.shop_operating_hours.opening_time', '06:00')
            ->assertJsonPath('data.settings.shop_operating_hours.is_open', true);
    }

    public function test_public_shop_hours_endpoint_returns_only_the_schedule(): void
    {
        Settings::set('shop_opening_time', '06:00');
        Settings::set('shop_closing_time', '20:00');

        $this->getJson('/api/v1/shop-hours')->assertOk()
            ->assertJsonPath('data.opening_time', '06:00')
            ->assertJsonPath('data.closing_time', '20:00')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonMissingPath('data.payment_method');
    }

    public function test_customer_preview_and_order_creation_are_blocked_when_closed(): void
    {
        Settings::set('shop_opening_time', '06:00');
        Settings::set('shop_closing_time', '20:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 21:00', 'Asia/Manila'));
        $customer = User::create(['name' => 'Customer', 'email' => 'closed@example.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
        $this->actingAs($customer, 'sanctum');
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Latte', 'base_price' => 100, 'is_available' => true]);
        $payload = ['order_type' => 'pickup', 'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->postJson('/api/v1/checkout/preview', $payload)->assertStatus(409)->assertJsonPath('message', 'Online ordering is currently closed. Please order during the shop operating hours.');
        $this->postJson('/api/v1/orders', $payload)->assertStatus(409);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_can_preview_and_place_order_during_open_hours(): void
    {
        Settings::set('shop_opening_time', '06:00');
        Settings::set('shop_closing_time', '20:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 09:00', 'Asia/Manila'));
        $customer = User::create(['name' => 'Customer', 'email' => 'open@example.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
        $this->actingAs($customer, 'sanctum');
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Latte', 'base_price' => 100, 'is_available' => true]);
        $payload = ['order_type' => 'pickup', 'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->postJson('/api/v1/checkout/preview', $payload)->assertOk();
        $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->assertDatabaseCount('orders', 1);
    }
}
