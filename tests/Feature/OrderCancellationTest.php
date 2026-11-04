<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_unpaid_qrph_order_while_awaiting_payment(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, 'awaiting_payment');
        $order->update(['payment_method' => 'qrph', 'payment_status' => 'unpaid']);
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'amount' => $order->total,
            'status' => 'pending',
            'qr_expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Wrong Order'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.payment_attempt_status', 'expired');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->qr_expires_at->isPast());
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'status' => 'cancelled',
            'changed_by' => $customer->id,
        ]);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Retry'])
            ->assertStatus(409);
        $this->assertSame(1, \DB::table('order_status_logs')
            ->where('order_id', $order->id)->where('status', 'cancelled')->count());
    }

    public function test_customer_cannot_cancel_a_paid_qrph_order_and_gets_staff_instruction(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $order->update(['payment_method' => 'qrph', 'payment_status' => 'paid']);
        Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'amount' => $order->total,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellation_requires_staff', true);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Wrong Order'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This payment is already confirmed. Contact Customer Service to request cancellation and any applicable refund.');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->payments()->first()->status);
        $this->assertDatabaseMissing('order_status_logs', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

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
