<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cancellation_requires_a_reason_and_cancels_immediately_once(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertUnprocessable();
        $this->assertSame('pending', $order->fresh()->status);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Ordered by mistake'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.reject_reason', 'Ordered by mistake');

        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'status' => 'cancelled',
            'changed_by' => $customer->id,
        ]);
        $this->assertNotNull(\DB::table('order_status_logs')
            ->where('order_id', $order->id)->where('status', 'cancelled')->value('created_at'));

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Retry'])
            ->assertStatus(409);
        $this->assertSame(1, \DB::table('order_status_logs')
            ->where('order_id', $order->id)->where('status', 'cancelled')->count());
        $this->getJson('/api/v1/orders')->assertOk()
            ->assertJsonFragment(['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_customer_archives_only_own_order_without_deleting_shop_records(): void
    {
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $order = $this->order($customer, 'cancelled');
        $otherOrder = $this->order($otherCustomer);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $archived = $order->fresh();
        $this->assertNotNull($archived->customer_archived_at);
        $this->assertNull($archived->deleted_at);
        $this->assertSame('cancelled', $archived->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonMissing(['id' => $order->id]);

        $this->deleteJson("/api/v1/orders/{$otherOrder->id}")->assertNotFound();
        $this->assertNull($otherOrder->fresh()->customer_archived_at);
    }
    public function test_customer_cannot_cancel_after_staff_confirmation_or_access_another_customers_order(): void
    {
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $delivered = $this->order($customer, 'delivered');
        $confirmed = $this->order($customer, 'confirmed');
        $otherOrder = $this->order($otherCustomer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$delivered->id}/cancel", ['reason' => 'Changed my mind'])
            ->assertStatus(409);
        $this->postJson("/api/v1/orders/{$confirmed->id}/cancel", ['reason' => 'Changed my mind'])
            ->assertStatus(409);
        $this->postJson("/api/v1/orders/{$otherOrder->id}/cancel", ['reason' => 'Not mine'])
            ->assertNotFound();
        $this->assertSame('delivered', $delivered->fresh()->status);
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        $this->assertSame('pending', $otherOrder->fresh()->status);
    }

    private function customer(): User
    {
        $suffix = Str::lower(Str::random(10));
        return User::create([
            'name' => 'Cancellation Customer',
            'username' => 'cancel' . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    private function order(User $customer, string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => 'KJM-CANCEL-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'delivery',
            'status' => $status,
            'subtotal' => 100,
            'total' => 130,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }
}
