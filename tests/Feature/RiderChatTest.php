<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryChatConversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiderChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_owner_and_currently_assigned_rider_can_read_and_send_messages(): void
    {
        [$customer, $rider, $order, $delivery] = $this->deliveryChat();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/rider-chat")
            ->assertOk()
            ->assertJsonPath('data.rider.name', $rider->name)
            ->assertJsonPath('data.messages', []);

        $this->postAs($customer, "/api/v1/orders/{$order->id}/rider-chat/messages", 'I am at the gate.')
            ->assertCreated()
            ->assertJsonPath('data.messages.0.body', 'I am at the gate.');

        $this->actingAs($rider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}/chat")
            ->assertOk()
            ->assertJsonPath('data.customer.name', $customer->name)
            ->assertJsonPath('data.messages.0.body', 'I am at the gate.');

        $this->postAs($rider, "/api/v1/rider/deliveries/{$delivery->id}/chat/messages", 'I am arriving.')
            ->assertCreated()
            ->assertJsonPath('data.messages.1.body', 'I am arriving.');

        $conversationId = DeliveryChatConversation::where('delivery_id', $delivery->id)->value('id');
        $this->assertDatabaseHas('delivery_chat_messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $rider->id,
            'body' => 'I am arriving.',
        ]);
    }

    public function test_other_customer_and_unassigned_rider_cannot_access_conversation(): void
    {
        [$customer, , $order, $delivery] = $this->deliveryChat();
        $otherCustomer = $this->user('customer');
        $otherRider = $this->user('rider');

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/rider-chat")
            ->assertNotFound();
        $this->actingAs($otherRider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}/chat")
            ->assertNotFound();
    }

    public function test_previous_rider_loses_access_after_reassignment(): void
    {
        [, $oldRider, , $delivery] = $this->deliveryChat();
        $newRider = $this->user('rider');
        $delivery->update(['rider_id' => $newRider->id]);

        $this->actingAs($oldRider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}/chat")
            ->assertNotFound();
        $this->actingAs($newRider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}/chat")
            ->assertOk();
    }

    public function test_empty_or_oversized_messages_are_rejected_and_closed_delivery_cannot_send(): void
    {
        [$customer, , $order, $delivery] = $this->deliveryChat();
        $this->postAs($customer, "/api/v1/orders/{$order->id}/rider-chat/messages", '')
            ->assertUnprocessable();
        $this->postAs($customer, "/api/v1/orders/{$order->id}/rider-chat/messages", str_repeat('x', 3001))
            ->assertUnprocessable();
        $delivery->update(['status' => 'delivered']);
        $this->postAs($customer, "/api/v1/orders/{$order->id}/rider-chat/messages", 'Thanks.')
            ->assertStatus(409);
    }

    public function test_staff_cannot_use_customer_or_rider_chat_endpoints(): void
    {
        [, , $order] = $this->deliveryChat();
        $staff = $this->user('staff');
        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/rider-chat")
            ->assertForbidden();
    }

    private function postAs(User $user, string $url, string $body)
    {
        return $this->actingAs($user, 'sanctum')->postJson($url, ['body' => $body]);
    }

    private function deliveryChat(): array
    {
        $customer = $this->user('customer');
        $rider = $this->user('rider');
        $order = Order::create([
            'order_number' => 'KJM-CHAT-' . Str::upper(Str::random(7)),
            'user_id' => $customer->id,
            'order_type' => 'delivery',
            'status' => 'on_the_way',
            'subtotal' => 100,
            'total' => 130,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'delivery_address' => 'Customer delivery address',
            'delivery_lat' => 15.96,
            'delivery_lng' => 120.54,
        ]);
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'on_the_way',
        ]);
        return [$customer, $rider, $order, $delivery];
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));
        return User::create([
            'name' => ucfirst($role) . ' Chat Test',
            'username' => $role . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
