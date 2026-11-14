<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffOrderItemNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_persists_each_product_note_and_staff_queue_shows_them_under_the_correct_item(): void
    {
        $customer = User::create([
            'name' => 'Notes Customer',
            'username' => 'notes' . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)) . '@example.test',
            'password' => 'password',
            'phone' => '09171234567',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Notes Test Category']);
        $products = collect([
            Product::create(['category_id' => $category->id, 'name' => 'Iced Caramel Macchiato', 'base_price' => 100, 'is_available' => true]),
            Product::create(['category_id' => $category->id, 'name' => 'Iced Spanish Latte', 'base_price' => 120, 'is_available' => true]),
            Product::create(['category_id' => $category->id, 'name' => 'Plain Americano', 'base_price' => 90, 'is_available' => true]),
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'contact_phone' => $customer->phone,
            'checkout_request_id' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $products[0]->id, 'quantity' => 1, 'notes' => "Less ice,\nextra caramel."],
                ['product_id' => $products[1]->id, 'quantity' => 2, 'notes' => 'No sugar.'],
                ['product_id' => $products[2]->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $order = Order::with(['items.options', 'items.product', 'user', 'delivery.rider'])
            ->findOrFail($response->json('data.order.id'));

        $this->assertSame(
            ["Less ice,\nextra caramel.", 'No sugar.', null],
            $order->items->pluck('notes')->all(),
        );

        $html = view('staff.orders._queue', ['orders' => collect([$order])])->render();

        $this->assertLessThan(
            strpos($html, 'Less ice,'),
            strpos($html, 'Iced Caramel Macchiato'),
        );
        $this->assertLessThan(
            strpos($html, 'No sugar.'),
            strpos($html, 'Iced Spanish Latte'),
        );
        $this->assertStringContainsString('whitespace-pre-wrap', $html);
        $this->assertSame(4, substr_count($html, 'Customer note'));
        $this->assertStringContainsString('Plain Americano', $html);
    }
}
