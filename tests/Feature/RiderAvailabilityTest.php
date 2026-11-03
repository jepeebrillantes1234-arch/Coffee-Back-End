<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_assignment_only_selects_an_online_active_approved_rider(): void
    {
        $offline = $this->rider(online: false);
        $online = $this->rider(online: true);
        $order = $this->readyOrder();

        $notice = app(OrderWorkflow::class)->assignAvailableRider($order);

        $this->assertNull($notice);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'rider_id' => $online->id,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseMissing('deliveries', ['rider_id' => $offline->id]);
    }

    public function test_manual_assignment_rejects_offline_rider_with_clear_admin_message(): void
    {
        $offline = $this->rider(online: false);
        $order = $this->readyOrder();
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->from('/admin/orders/'.$order->id)
            ->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $offline->id])
            ->assertRedirect('/admin/orders/'.$order->id)
            ->assertSessionHas('error', 'The selected rider must be ONLINE before a delivery can be assigned.');

        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_online_rider_can_be_manually_assigned_and_accept_the_delivery(): void
    {
        $online = $this->rider(online: true);
        $order = $this->readyOrder();
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->from('/admin/orders/'.$order->id)
            ->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $online->id])
            ->assertRedirect('/admin/orders/'.$order->id)
            ->assertSessionHas('success', 'Rider assigned.');

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($online, 'sanctum')
            ->postJson("/api/v1/rider/deliveries/{$delivery->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame('accepted', $delivery->fresh()->status);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $online->id,
            'title' => 'New Delivery Assignment',
            'type' => 'delivery',
        ]);
    }

    public function test_offline_rider_cannot_accept_a_newly_assigned_delivery(): void
    {
        $rider = $this->rider(online: false);
        $order = $this->readyOrder();
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($rider, 'sanctum')
            ->postJson("/api/v1/rider/deliveries/{$delivery->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('message', 'You must be an active, approved, ONLINE rider to accept a new delivery.');

        $this->assertSame('assigned', $delivery->fresh()->status);
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_active_delivery_remains_accessible_and_manageable_after_rider_goes_offline(): void
    {
        $rider = $this->rider(online: true);
        $order = $this->readyOrder();
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'accepted',
        ]);
        $order->update(['status' => 'rider_assigned']);

        $this->actingAs($rider, 'sanctum')
            ->putJson('/api/v1/rider/availability', ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $this->getJson('/api/v1/rider/deliveries')
            ->assertOk()
            ->assertJsonFragment(['id' => $delivery->id, 'status' => 'accepted']);

        $this->postJson("/api/v1/rider/deliveries/{$delivery->id}/pickup")
            ->assertOk()
            ->assertJsonPath('data.status', 'picked_up');

        $this->assertSame('picked_up', $delivery->fresh()->status);
        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_online_rider_can_receive_multiple_active_deliveries(): void
    {
        $rider = $this->rider(online: true);
        $existingOrder = $this->readyOrder();
        $existingOrder->update(['status' => 'on_the_way']);
        Delivery::create([
            'order_id' => $existingOrder->id,
            'rider_id' => $rider->id,
            'status' => 'on_the_way',
        ]);
        $newOrder = $this->readyOrder();

        $notice = app(OrderWorkflow::class)->assignAvailableRider($newOrder);

        $this->assertNull($notice);
        $this->assertSame(2, Delivery::where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'on_the_way'])
            ->count());
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $newOrder->id,
            'rider_id' => $rider->id,
            'status' => 'assigned',
        ]);
    }

    public function test_assigned_delivery_is_visible_only_to_its_rider(): void
    {
        $assignedRider = $this->rider(online: true);
        $otherRider = $this->rider(online: true);
        $order = $this->readyOrder();
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $assignedRider->id])
            ->assertSessionHas('success', 'Rider assigned.');
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($assignedRider, 'sanctum')
            ->getJson('/api/v1/rider/deliveries')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $delivery->id);

        $this->actingAs($otherRider, 'sanctum')
            ->getJson('/api/v1/rider/deliveries')
            ->assertOk()
            ->assertJsonPath('data.items', []);

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
    }

    public function test_competing_assignment_requests_cannot_assign_one_order_twice(): void
    {
        $firstRider = $this->rider(online: true);
        $secondRider = $this->rider(online: true);
        $order = $this->readyOrder();
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $firstRider->id])
            ->assertSessionHas('success', 'Rider assigned.');

        $this->actingAs($admin)
            ->from('/admin/orders/'.$order->id)
            ->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $secondRider->id])
            ->assertRedirect('/admin/orders/'.$order->id)
            ->assertSessionHas('error', 'This order is no longer available for assignment.');

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'rider_id' => $firstRider->id,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseMissing('deliveries', [
            'order_id' => $order->id,
            'rider_id' => $secondRider->id,
        ]);
    }

    public function test_ready_unassigned_delivery_remains_on_staff_assignment_queue(): void
    {
        $order = $this->readyOrder();
        $staff = $this->user('staff');
        $this->actingAs($staff)->post(route('staff.shift.open'), ['opening_fund' => 0])->assertRedirect(route('staff.orders'));

        $this->actingAs($staff)
            ->get('/staff/orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Assign to an online rider');
    }

    public function test_declined_delivery_can_be_reassigned_without_creating_a_second_record(): void
    {
        $previousRider = $this->rider(online: true);
        $newRider = $this->rider(online: true);
        $order = $this->readyOrder();
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $previousRider->id,
            'status' => 'declined',
        ]);
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Assign to selected rider');

        $this->post('/admin/orders/'.$order->id.'/assign', ['rider_id' => $newRider->id])
            ->assertSessionHas('success', 'Rider assigned.');

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
        $this->assertSame($newRider->id, $delivery->fresh()->rider_id);
        $this->assertSame('assigned', $delivery->fresh()->status);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $newRider->id,
            'title' => 'Delivery reassigned',
            'type' => 'delivery',
        ]);
    }

    public function test_delivered_order_is_removed_from_active_deliveries_and_appears_in_history(): void
    {
        $rider = $this->rider(online: false);
        $order = $this->readyOrder();
        $order->update(['status' => 'delivered']);
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/rider/deliveries')
            ->assertOk()
            ->assertJsonPath('data.items', []);

        $this->getJson('/api/v1/rider/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $delivery->id)
            ->assertJsonPath('data.items.0.status', 'delivered');
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

    private function readyOrder(): Order
    {
        $customer = $this->user('customer');

        return Order::create([
            'user_id' => $customer->id,
            'order_number' => 'KJM-AVAIL-'.Str::upper(Str::random(8)),
            'order_type' => 'delivery',
            'status' => 'ready',
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
            'name' => ucfirst($role).' Availability Test',
            'username' => $role.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
