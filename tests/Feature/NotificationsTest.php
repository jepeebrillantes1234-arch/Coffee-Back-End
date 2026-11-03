<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Services\PaymentGateway;
use App\Services\FcmPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_rider_can_list_only_their_own_notifications(): void
    {
        $customer = $this->user('customer');
        $rider = $this->user('rider');
        $other = $this->user('customer');

        $this->notification($customer, 'Customer notice');
        $this->notification($rider, 'Rider notice');
        $this->notification($other, 'Other customer notice');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Customer notice');

        $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Rider notice');
    }

    public function test_assignment_notification_goes_to_the_selected_online_rider_only(): void
    {
        $customer = $this->user('customer');
        $offline = $this->rider(online: false);
        $online = $this->rider(online: true);
        $order = $this->readyOrder($customer);
        $offline->forceFill(['fcm_token' => 'test-offline-rider-token'])->save();
        $online->forceFill(['fcm_token' => 'test-online-rider-token'])->save();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function ($message) use ($order) {
            $payload = $message->jsonSerialize();

            return ($payload['notification']['title'] ?? null) === 'New Delivery Assignment'
                && ($payload['notification']['body'] ?? null) === 'You have a new delivery request. Tap to view the delivery.'
                && ($payload['data']['type'] ?? null) === 'delivery_assigned'
                && ($payload['data']['order_id'] ?? null) === (string) $order->id;
        }))->andReturn(['name' => 'mock-delivery-message-id']);
        $this->instance(Messaging::class, $messaging);

        app(OrderWorkflow::class)->assignRider($order, (int) $online->id);

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $online->id,
            'type' => 'delivery',
            'title' => 'New Delivery Assignment',
            'message' => 'You have a new delivery request. Tap to view the delivery.',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $offline->id,
            'type' => 'delivery',
        ]);

        $record = DB::table('app_notifications')
            ->where('user_id', $online->id)
            ->where('type', 'delivery')
            ->first();
        $data = json_decode($record->data, true);
        $this->assertSame((string) $delivery->id, $data['delivery_id']);
        $this->assertSame('assigned', $data['status']);
        $this->assertArrayNotHasKey('phone', $data);
        $this->assertArrayNotHasKey('delivery_address', $data);
    }

    public function test_rider_delivery_progress_notifies_only_the_order_customer(): void
    {
        $customer = $this->user('customer');
        $otherCustomer = $this->user('customer');
        $rider = $this->user('rider');
        $order = $this->order($customer, 'picked_up');
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'picked_up',
        ]);

        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/rider/deliveries/{$delivery->id}/start")
            ->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'type' => 'order',
        ]);
        $record = DB::table('app_notifications')
            ->where('user_id', $customer->id)
            ->where('type', 'order')
            ->latest('id')
            ->first();
        $this->assertSame('on_the_way', json_decode($record->data, true)['status']);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $otherCustomer->id,
            'type' => 'order',
        ]);
    }

    public function test_cancelling_an_assigned_order_notifies_its_current_rider(): void
    {
        $customer = $this->user('customer');
        $rider = $this->rider(online: false);
        $order = $this->order($customer, 'pending');
        Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
        ]);

        app(OrderWorkflow::class)->cancel($order, 'Customer request');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $rider->id,
            'type' => 'delivery',
            'title' => 'Delivery cancelled',
        ]);
    }

    public function test_reassignment_notifies_the_new_online_rider(): void
    {
        $customer = $this->user('customer');
        $previousRider = $this->rider(online: true);
        $newRider = $this->rider(online: true);
        $order = $this->readyOrder($customer);
        Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $previousRider->id,
            'status' => 'assigned',
        ]);

        app(OrderWorkflow::class)->assignRider($order, (int) $newRider->id);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $newRider->id,
            'type' => 'delivery',
            'title' => 'Delivery reassigned',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $previousRider->id,
            'type' => 'delivery',
        ]);
    }

    public function test_ready_status_notifies_the_order_customer(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'preparing');

        app(OrderWorkflow::class)->ready($order);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'type' => 'order',
            'title' => 'Order Ready for Pickup',
        ]);
    }

    public function test_order_status_push_uses_clear_copy_and_deduplicates_the_same_status_log(): void
    {
        $customer = $this->user('customer');
        $customer->forceFill(['fcm_token' => 'test-confirmation-device-token'])->save();
        $order = $this->order($customer, 'confirmed');
        $statusLogId = DB::table('order_status_logs')->insertGetId([
            'order_id' => $order->id,
            'status' => 'confirmed',
            'changed_by' => null,
            'created_at' => now(),
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function ($message) use ($order) {
            $payload = $message->jsonSerialize();

            return ($payload['notification']['title'] ?? null) === 'Order Confirmed!'
                && ($payload['notification']['body'] ?? null) === 'Your order has been accepted by KJM Coffee. We will start preparing your order soon.'
                && ($payload['data']['order_id'] ?? null) === (string) $order->id;
        }))->andReturn(['name' => 'mock-message-id']);
        $this->instance(Messaging::class, $messaging);

        \App\Support\Notifier::order($order, 'confirmed');
        \App\Support\Notifier::order($order, 'confirmed');

        $records = DB::table('app_notifications')->where('user_id', $customer->id)->where('type', 'order')->get();
        $this->assertCount(1, $records);
        $this->assertSame('Order Confirmed!', $records[0]->title);
        $this->assertSame('Your order has been accepted by KJM Coffee. We will start preparing your order soon.', $records[0]->message);
        $data = json_decode($records[0]->data, true);
        $this->assertSame((string) $statusLogId, $data['status_log_id']);
        $this->assertSame('sent', $data['fcm_delivery_status']);
    }

    public function test_failed_confirmation_push_retries_without_duplicate_notification_record(): void
    {
        $customer = $this->user('customer');
        $customer->forceFill(['fcm_token' => 'test-retry-device-token'])->save();
        $order = $this->order($customer, 'confirmed');
        DB::table('order_status_logs')->insert([
            'order_id' => $order->id,
            'status' => 'confirmed',
            'changed_by' => null,
            'created_at' => now(),
        ]);

        $attempt = 0;
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->twice()->andReturnUsing(function () use (&$attempt) {
            $attempt++;
            if ($attempt === 1) {
                throw new \RuntimeException('mock provider failure');
            }

            return ['name' => 'mock-message-id'];
        });
        $this->instance(Messaging::class, $messaging);
        Log::shouldReceive('warning')->once()->with(
            'Unable to send Firebase push notification.',
            Mockery::on(fn ($context) => $context['user_id'] === $customer->id
                && $context['exception'] === \RuntimeException::class
                && !array_key_exists('message', $context)
                && !array_key_exists('token', $context))
        );

        \App\Support\Notifier::order($order, 'confirmed');
        $record = DB::table('app_notifications')->where('user_id', $customer->id)->where('type', 'order')->first();
        $this->assertSame('failed', json_decode($record->data, true)['fcm_delivery_status']);

        \App\Support\Notifier::order($order, 'confirmed');
        \App\Support\Notifier::order($order, 'confirmed');

        $records = DB::table('app_notifications')->where('user_id', $customer->id)->where('type', 'order')->get();
        $this->assertCount(1, $records);
        $this->assertSame('sent', json_decode($records[0]->data, true)['fcm_delivery_status']);
    }

    public function test_order_and_rider_workflow_notifies_each_real_status_transition(): void
    {
        Storage::fake('public');
        $customer = $this->user('customer');
        $rider = $this->rider(online: true);
        $order = $this->order($customer, 'pending');
        $order->update(['payment_method' => 'gcash', 'payment_status' => 'paid']);
        $workflow = app(OrderWorkflow::class);

        $workflow->confirm($order);
        $workflow->preparing($order);
        $workflow->ready($order);
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($rider, 'sanctum')->postJson("/api/v1/rider/deliveries/{$delivery->id}/accept")->assertOk();
        $this->actingAs($rider, 'sanctum')->postJson("/api/v1/rider/deliveries/{$delivery->id}/pickup")->assertOk();
        $this->actingAs($rider, 'sanctum')->postJson("/api/v1/rider/deliveries/{$delivery->id}/start")->assertOk();
        $this->actingAs($rider, 'sanctum')
            ->post("/api/v1/rider/deliveries/{$delivery->id}/delivered", [
                'proof_photo' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $expected = [
            'confirmed' => [
                'Order Confirmed!',
                'Your order has been accepted by KJM Coffee. We will start preparing your order soon.',
            ],
            'preparing' => ['Preparing Your Order', 'Your order is now being prepared.'],
            'ready' => ['Order Ready for Pickup', 'Your order is ready for rider pickup.'],
            'picked_up' => ['Order Picked Up', 'Your rider has picked up your order.'],
            'on_the_way' => ['Your Order Is On the Way!', 'Your rider is now delivering your order.'],
            'delivered' => ['Order Delivered!', 'Your order has been delivered.'],
        ];

        foreach ($expected as $status => [$title, $message]) {
            $record = DB::table('app_notifications')
                ->where('user_id', $customer->id)
                ->where('type', 'order')
                ->where('title', $title)
                ->first();
            $this->assertNotNull($record, "Missing notification for {$status}");
            $this->assertSame($message, $record->message);
            $this->assertSame($status, json_decode($record->data, true)['status']);
        }
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_rider_chat_message_notifies_only_the_order_customer_with_safe_payload(): void
    {
        $customer = $this->user('customer');
        $rider = $this->user('rider');
        $otherCustomer = $this->user('customer');
        $order = $this->order($customer, 'on_the_way');
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'on_the_way',
        ]);
        $privateBody = 'My private phone number is 09171234567';

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/rider-chat/messages", ['body' => 'I am waiting outside.'])
            ->assertCreated();
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $rider->id,
            'type' => 'rider_chat',
        ]);

        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/rider/deliveries/{$delivery->id}/chat/messages", ['body' => $privateBody])
            ->assertCreated();

        $record = DB::table('app_notifications')->where('user_id', $customer->id)->where('type', 'rider_chat')->first();
        $this->assertNotNull($record);
        $this->assertSame('New Message from Your Rider', $record->title);
        $this->assertSame('A new message is waiting in your order chat.', $record->message);
        $data = json_decode($record->data, true);
        $this->assertSame((string) $order->id, $data['order_id']);
        $this->assertSame((string) $delivery->id, $data['delivery_id']);
        $this->assertArrayHasKey('conversation_id', $data);
        $this->assertArrayHasKey('message_id', $data);
        $this->assertStringNotContainsString('09171234567', $record->message);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $otherCustomer->id, 'type' => 'rider_chat']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $rider->id, 'type' => 'rider_chat']);
    }

    public function test_fcm_payload_contains_notification_and_routing_data(): void
    {
        $user = $this->user('customer');
        $user->forceFill(['fcm_token' => 'test-device-token'])->save();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function ($message) {
            $payload = $message->jsonSerialize();

            return ($payload['notification']['title'] ?? null) === 'Order Ready for Pickup'
                && ($payload['notification']['body'] ?? null) === 'Your order is ready for rider pickup.'
                && ($payload['data']['type'] ?? null) === 'order'
                && ($payload['data']['order_id'] ?? null) === '123';
        }))->andReturn([]);
        $this->instance(Messaging::class, $messaging);

        $result = app(FcmPushService::class)->sendToUser($user, 'Order Ready for Pickup', 'Your order is ready for rider pickup.', [
            'type' => 'order',
            'order_id' => 123,
        ]);
        $this->assertSame('sent', $result);
    }

    public function test_unregistered_fcm_token_is_cleared_without_logging_the_token(): void
    {
        $user = $this->user('customer');
        $deviceToken = 'test-invalid-device-token';
        $user->forceFill(['fcm_token' => $deviceToken, 'fcm_platform' => 'android'])->save();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->andThrow(NotFound::becauseTokenNotFound($deviceToken));
        $this->instance(Messaging::class, $messaging);
        Log::shouldReceive('warning')->once()->with(
            'Firebase rejected a registered device token; it was cleared.',
            Mockery::on(fn ($context) => $context['user_id'] === $user->id
                && $context['exception'] === NotFound::class
                && !str_contains(json_encode($context), $deviceToken))
        );

        $result = app(FcmPushService::class)->sendToUser($user, 'Test', 'Test body', ['type' => 'order']);

        $this->assertSame('invalid_token', $result);
        $this->assertNull($user->fresh()->fcm_token);
        $this->assertNull($user->fresh()->fcm_platform);
    }

    public function test_first_verified_payment_notifies_the_customer_without_provider_requests(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'pending');

        $accepted = app(PaymentGateway::class)->markPaid($order, 'fake-payment-id', 13000);

        $this->assertTrue($accepted);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $record = DB::table('app_notifications')
            ->where('user_id', $customer->id)
            ->where('type', 'payment')
            ->first();
        $data = json_decode($record->data, true);
        $this->assertSame('payment_confirmed', $data['event']);
        $this->assertSame('pending', $data['order_status']);
        $this->assertSame('paid', $data['payment_status']);
        $this->assertArrayNotHasKey('amount', $data);
    }
    private function notification(User $user, string $title): void
    {
        DB::table('app_notifications')->insert([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $title,
            'type' => 'general',
            'data' => json_encode(['type' => 'general']),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rider(bool $online): User
    {
        $rider = $this->user('rider');
        RiderProfile::create([
            'user_id' => $rider->id,
            'is_approved' => true,
            'is_available' => $online,
        ]);

        return $rider;
    }

    private function readyOrder(User $customer): Order
    {
        return $this->order($customer, 'ready');
    }

    private function order(User $customer, string $status): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'KJM-NOTIFY-'.Str::upper(Str::random(8)),
            'order_type' => 'delivery',
            'status' => $status,
            'subtotal' => 100,
            'total' => 130,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));

        return User::create([
            'name' => ucfirst($role).' Notification Test',
            'username' => $role.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
