<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryChatConversation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RiderChatTest extends TestCase
{
    use RefreshDatabase;

    private string $chatStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kjm-chat-'.Str::uuid();
        mkdir($this->chatStoragePath, 0777, true);
        config(['filesystems.disks.local.root' => $this->chatStoragePath]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('local');
        File::deleteDirectory($this->chatStoragePath);
        parent::tearDown();
    }

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
            ->assertJsonPath('data.message.body', 'I am at the gate.');

        $this->actingAs($rider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}/chat")
            ->assertOk()
            ->assertJsonPath('data.customer.name', $customer->name)
            ->assertJsonPath('data.messages.0.body', 'I am at the gate.');

        $this->postAs($rider, "/api/v1/rider/deliveries/{$delivery->id}/chat/messages", 'I am arriving.')
            ->assertCreated()
            ->assertJsonPath('data.message.body', 'I am arriving.');

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

    public function test_message_retry_with_the_same_client_id_creates_only_one_message(): void
    {
        [$customer, , $order] = $this->deliveryChat();
        $clientId = (string) Str::uuid();
        $url = "/api/v1/orders/{$order->id}/rider-chat/messages";

        $this->actingAs($customer, 'sanctum')->postJson($url, [
            'body' => 'At the gate.', 'client_message_id' => $clientId,
        ])->assertCreated()->assertJsonPath('data.message.client_message_id', $clientId);
        $this->actingAs($customer, 'sanctum')->postJson($url, [
            'body' => 'At the gate.', 'client_message_id' => $clientId,
        ])->assertOk()->assertJsonPath('data.message.client_message_id', $clientId);

        $this->assertDatabaseCount('delivery_chat_messages', 1);
    }

    public function test_older_client_without_an_idempotency_key_can_still_send_text(): void
    {
        [$customer, , $order] = $this->deliveryChat();
        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/rider-chat/messages", ['body' => 'Still here.'])
            ->assertCreated();

        $this->assertNotEmpty($response->json('data.message.client_message_id'));
        $this->assertDatabaseCount('delivery_chat_messages', 1);
    }

    public function test_customer_and_rider_can_send_private_two_megabyte_or_smaller_photos(): void
    {
        [$customer, $rider, $order, $delivery] = $this->deliveryChat();
        $customerMessage = $this->actingAs($customer, 'sanctum')
            ->post("/api/v1/orders/{$order->id}/rider-chat/messages", [
                'client_message_id' => (string) Str::uuid(),
                'attachment' => $this->validPngUpload(),
            ], ['Accept' => 'application/json'])
            ->assertCreated();
        $customerMessage->assertJsonPath('data.message.attachment_mime', 'image/png');

        $riderMessage = $this->actingAs($rider, 'sanctum')
            ->post("/api/v1/rider/deliveries/{$delivery->id}/chat/messages", [
                'client_message_id' => (string) Str::uuid(),
                'attachment' => $this->validPngUpload('rider.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();
        $this->assertNotEmpty($riderMessage->json('data.message.attachment_url'));

        $customerAttachmentUrl = parse_url($customerMessage->json('data.message.attachment_url'), PHP_URL_PATH);
        $this->actingAs($customer, 'sanctum')->get($customerAttachmentUrl)->assertOk();
        $this->actingAs($rider, 'sanctum')->get($customerAttachmentUrl)->assertOk();
        $this->actingAs($this->user('customer'), 'sanctum')->get($customerAttachmentUrl)->assertNotFound();
    }

    public function test_chat_rejects_images_over_two_megabytes_and_non_image_files(): void
    {
        [$customer, , $order] = $this->deliveryChat();
        $url = "/api/v1/orders/{$order->id}/rider-chat/messages";
        $this->actingAs($customer, 'sanctum')->post($url, [
            'client_message_id' => (string) Str::uuid(),
            'attachment' => UploadedFile::fake()->createWithContent(
                'large.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/6N8AAAAASUVORK5CYII=').str_repeat('x', 2 * 1024 * 1024),
            ),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->actingAs($customer, 'sanctum')->post($url, [
            'client_message_id' => (string) Str::uuid(),
            'attachment' => UploadedFile::fake()->create('payload.gif', 20, 'image/gif'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    private function postAs(User $user, string $url, string $body)
    {
        return $this->actingAs($user, 'sanctum')->postJson($url, [
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
    }

    private function validPngUpload(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/6N8AAAAASUVORK5CYII='),
        );
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
