<?php

namespace Tests\Feature;

use App\Events\LiveUpdate;
use App\Models\Address;
use App\Models\Membership;
use App\Models\MembershipCardDelivery;
use App\Models\MembershipPayment;
use App\Models\RiderProfile;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembershipCardDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['view.compiled' => sys_get_temp_dir(), 'logging.default' => 'stderr']);
        app()->forgetInstance('blade.compiler');
        app()->forgetInstance('view.engine.resolver');
    }

    public function test_customer_with_active_paid_membership_can_request_delivery_and_address_is_snapshotted(): void
    {
        [$customer, $membership, $address] = $this->eligibleCustomer();
        $original = $address->formatted_address;

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/membership/card-deliveries', [
            'address_id' => $address->id,
            'delivery_notes' => 'Blue gate beside the bakery',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.address.full_address', $original)
            ->assertJsonPath('data.address.barangay', 'San Roque')
            ->assertJsonPath('data.address.city', 'San Carlos City')
            ->assertJsonPath('data.address.notes', 'Blue gate beside the bakery');

        $deliveryId = $response->json('data.id');
        $address->update(['formatted_address' => 'Changed after request', 'latitude' => 15.0, 'longitude' => 120.0]);

        $this->assertDatabaseHas('membership_card_deliveries', [
            'id' => $deliveryId,
            'membership_id' => $membership->id,
            'user_id' => $customer->id,
            'address_snapshot' => $original,
            'destination_latitude' => 15.2,
            'destination_longitude' => 120.1,
        ]);
        $this->assertDatabaseHas('membership_payments', ['membership_id' => $membership->id, 'status' => 'paid']);
    }

    public function test_request_requires_active_paid_membership_and_customer_owned_saved_address(): void
    {
        $customer = $this->user('customer');
        $address = $this->address($customer);
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/membership/card-deliveries', [
            'address_id' => $address->id,
        ])->assertStatus(409);

        [$eligible, , $eligibleAddress] = $this->eligibleCustomer();
        $this->actingAs($eligible, 'sanctum')->postJson('/api/v1/membership/card-deliveries', [
            'address_id' => $address->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('membership_card_deliveries', 0);
    }

    public function test_duplicate_card_delivery_request_is_rejected(): void
    {
        [$customer, , $address] = $this->eligibleCustomer();
        Event::fake([LiveUpdate::class]);
        $payload = ['address_id' => $address->id];

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/membership/card-deliveries', $payload)->assertCreated();
        $this->postJson('/api/v1/membership/card-deliveries', $payload)->assertStatus(409);
        $this->assertDatabaseCount('membership_card_deliveries', 1);
        Event::assertDispatchedTimes(LiveUpdate::class, 1);
    }

    public function test_staff_and_admin_dashboard_poll_each_new_card_request_without_repeating_seen_ids(): void
    {
        $eligibleCustomers = [$this->eligibleCustomer(), $this->eligibleCustomer()];
        Event::fake([LiveUpdate::class]);
        $requests = [];
        foreach ($eligibleCustomers as [$customer, $membership, $address]) {
            $this->actingAs($customer, 'sanctum')->postJson('/api/v1/membership/card-deliveries', [
                'address_id' => $address->id,
            ])->assertCreated();
            $requests[] = [$customer, $membership, MembershipCardDelivery::where('membership_id', $membership->id)->firstOrFail()];
        }

        Event::assertDispatchedTimes(LiveUpdate::class, 2);
        Event::assertDispatched(LiveUpdate::class, fn (LiveUpdate $event) => $event->kind === 'membership_card_delivery');

        $staff = $this->user('staff');
        $url = route('staff.membership-card-deliveries.notifications');
        $this->actingAs($staff)->getJson($url)
            ->assertOk()
            ->assertJsonPath('pending_count', 2)
            ->assertJsonCount(2, 'new_requests')
            ->assertJsonPath('new_requests.0.member_id', $requests[0][1]->member_id)
            ->assertJsonPath('new_requests.0.customer', $requests[0][0]->name)
            ->assertJsonPath('new_requests.0.url', route('staff.membership-card-deliveries.index'));

        $this->actingAs($staff)->getJson($url.'?after_id='.$requests[0][2]->id)
            ->assertOk()
            ->assertJsonCount(1, 'new_requests')
            ->assertJsonPath('new_requests.0.id', $requests[1][2]->id);
        $this->actingAs($staff)->getJson($url.'?after_id='.$requests[1][2]->id)
            ->assertOk()->assertJsonCount(0, 'new_requests');

        $admin = $this->user('admin');
        $this->actingAs($admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('pending_count', 2)
            ->assertJsonCount(2, 'pending_requests')
            ->assertJsonPath('new_requests.0.url', route('admin.membership-card-deliveries.index'));

        $customer = $requests[0][0];
        $this->actingAs($customer)->getJson($url)->assertForbidden();
        $this->actingAs($this->user('rider'))->getJson($url)->assertForbidden();
    }

    public function test_staff_and_admin_can_preview_the_actual_membership_card_for_a_delivery(): void
    {
        $delivery = $this->delivery();
        $memberId = $delivery->membership->member_id;

        $this->actingAs($this->user('staff'))
            ->get(route('staff.membership-card-deliveries.index'))
            ->assertOk()
            ->assertSee('View Card')
            ->assertSee('data-membership-qr="'.$memberId.'"', false)
            ->assertSee('images/membership-cards/card.png')
            ->assertSee('images/membership-cards/card1.png');

        $this->actingAs($this->user('admin'))
            ->get(route('admin.membership-card-deliveries.index'))
            ->assertOk()
            ->assertSee('View Card')
            ->assertSee('data-membership-qr="'.$memberId.'"', false)
            ->assertSee('images/membership-cards/card.png')
            ->assertSee('images/membership-cards/card1.png');
    }

    public function test_staff_and_admin_card_preview_uses_active_admin_uploaded_artwork(): void
    {
        $this->delivery();
        Settings::set('membership_card_design_active', '1');
        Settings::set('membership_card_front_design', 'membership-cards/front-test.png');
        Settings::set('membership_card_back_design', 'membership-cards/back-test.png');
        $front = Storage::disk('public')->url('membership-cards/front-test.png');
        $back = Storage::disk('public')->url('membership-cards/back-test.png');

        $this->actingAs($this->user('staff'))
            ->get(route('staff.membership-card-deliveries.index'))
            ->assertOk()->assertSee($front)->assertSee($back);
        $this->actingAs($this->user('admin'))
            ->get(route('admin.membership-card-deliveries.index'))
            ->assertOk()->assertSee($front)->assertSee($back);
    }

    public function test_staff_prepares_card_and_assigns_only_an_online_approved_rider(): void
    {
        $staff = $this->user('staff');
        $delivery = $this->delivery();
        $rider = $this->rider(true);
        $offline = $this->rider(false);

        $this->actingAs($staff)->post(route('staff.membership-card-deliveries.preparing', $delivery->id))
            ->assertRedirect()->assertSessionHas('success');
        $this->post(route('staff.membership-card-deliveries.ready', $delivery->id))
            ->assertRedirect()->assertSessionHas('success');
        $this->post(route('staff.membership-card-deliveries.assign', $delivery->id), ['rider_id' => $offline->id])
            ->assertRedirect()->assertSessionHas('error', 'The selected rider must be active, approved, and ONLINE.');
        $this->post(route('staff.membership-card-deliveries.assign', $delivery->id), ['rider_id' => $rider->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('membership_card_deliveries', [
            'id' => $delivery->id,
            'status' => 'ready_for_delivery',
            'rider_id' => $rider->id,
            'assigned_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'preparing_card', 'module' => 'membership_card_deliveries']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'ready_for_delivery', 'module' => 'membership_card_deliveries']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'assign_rider', 'module' => 'membership_card_deliveries']);
    }

    public function test_only_assigned_rider_can_access_update_and_record_membership_delivery_gps(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 1800,
                'duration' => 360,
                'geometry' => ['coordinates' => [[120.2, 15.3], [120.15, 15.25], [120.1, 15.2]]],
            ]],
        ])]);
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        $rider = $this->rider(false);
        $other = $this->rider(true);
        $delivery = $this->delivery(['rider_id' => $rider->id, 'status' => 'ready_for_delivery', 'assigned_at' => now()]);
        $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/rider/membership-card-deliveries/'.$delivery->id.'/start')
            ->assertOk()->assertJsonPath('data.status', 'out_for_delivery');

        $this->postJson('/api/v1/rider/membership-card-delivery-location', [
            'delivery_id' => $delivery->id, 'latitude' => 15.3, 'longitude' => 120.2, 'accuracy' => 8,
        ])->assertOk();

        $this->getJson('/api/v1/rider/membership-card-deliveries/'.$delivery->id)
            ->assertOk()
            ->assertJsonPath('data.rider_location.lat', 15.3)
            ->assertJsonPath('data.address.lat', 15.2)
            ->assertJsonPath('data.route.status', 'available')
            ->assertJsonPath('data.route.points.0.lat', 15.3)
            ->assertJsonPath('data.route.points.2.lng', 120.1);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/rider/membership-card-deliveries/'.$delivery->id)->assertNotFound();
        $this->postJson('/api/v1/rider/membership-card-delivery-location', [
            'delivery_id' => $delivery->id, 'latitude' => 15.4, 'longitude' => 120.4,
        ])->assertNotFound();

        $this->assertDatabaseCount('membership_card_delivery_locations', 1);
        $this->assertDatabaseCount('rider_locations', 0);

        $this->actingAs($rider, 'sanctum')->putJson('/api/v1/rider/availability', ['is_available' => false])->assertOk();
        $this->getJson('/api/v1/rider/membership-card-deliveries/'.$delivery->id)->assertOk();
        $this->postJson('/api/v1/rider/membership-card-deliveries/'.$delivery->id.'/delivered')
            ->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $rider->id, 'action' => 'start_delivery', 'module' => 'membership_card_deliveries']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $rider->id, 'action' => 'complete_delivery', 'module' => 'membership_card_deliveries']);
        $this->assertDatabaseCount('rider_locations', 0);
    }

    public function test_admin_can_oversee_all_membership_card_deliveries_but_staff_cannot_open_admin_page(): void
    {
        $this->delivery();
        $admin = $this->user('admin');
        $staff = $this->user('staff');

        $this->actingAs($admin)->get(route('admin.membership-card-deliveries.index'))
            ->assertOk()->assertSee('Membership Card Deliveries');
        $this->actingAs($staff)->get(route('admin.membership-card-deliveries.index'))->assertForbidden();
    }

    private function eligibleCustomer(): array
    {
        $customer = $this->user('customer');
        $membership = Membership::create([
            'user_id' => $customer->id,
            'member_id' => 'KJM-'.Str::upper(Str::random(10)),
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addYear(),
            'points_balance' => 0,
        ]);
        MembershipPayment::create([
            'membership_id' => $membership->id,
            'provider' => 'paymongo',
            'payment_reference' => 'KJMM-'.Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        return [$customer, $membership, $this->address($customer)];
    }

    private function address(User $user): Address
    {
        return Address::create([
            'user_id' => $user->id, 'label' => 'Home',
            'full_address' => 'Block 1, San Roque, San Carlos City',
            'formatted_address' => 'Block 1, San Roque, San Carlos City',
            'street' => 'Block 1', 'barangay' => 'San Roque', 'city' => 'San Carlos City', 'province' => 'Pangasinan',
            'latitude' => 15.2, 'longitude' => 120.1, 'is_default' => true, 'notes' => 'Blue gate',
        ]);
    }

    private function delivery(array $overrides = []): MembershipCardDelivery
    {
        [$customer, $membership, $address] = $this->eligibleCustomer();
        return MembershipCardDelivery::create(array_merge([
            'membership_id' => $membership->id, 'user_id' => $customer->id, 'requested_address_id' => $address->id,
            'customer_name_snapshot' => $customer->name, 'customer_phone_snapshot' => $customer->phone,
            'address_snapshot' => $address->formatted_address, 'street_snapshot' => $address->street,
            'barangay_snapshot' => $address->barangay, 'city_snapshot' => $address->city, 'province_snapshot' => $address->province,
            'destination_latitude' => $address->latitude, 'destination_longitude' => $address->longitude,
            'status' => 'pending',
        ], $overrides));
    }

    private function rider(bool $online): User
    {
        $rider = $this->user('rider');
        RiderProfile::create(['user_id' => $rider->id, 'is_approved' => true, 'is_available' => $online]);
        return $rider;
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));
        return User::create([
            'name' => ucfirst($role).' Card Delivery Test', 'username' => $role.$suffix,
            'email' => $suffix.'@example.test', 'phone' => '09'.random_int(100000000, 999999999),
            'password' => 'password', 'role' => $role, 'status' => 'active',
        ]);
    }
}
