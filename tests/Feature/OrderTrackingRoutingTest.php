<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTrackingRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tracking_returns_osrm_road_geometry_duration_and_throttles_movement_refreshes(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fake(['router.project-osrm.org/route/v1/driving/*' => Http::response($this->osrmSuccess(), 200)]);
        [$customer, $order, $profile] = $this->deliveryOrder();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.route.status', 'available')
            ->assertJsonPath('data.route.duration_seconds', 420)
            ->assertJsonPath('data.route.distance_meters', 1800)
            ->assertJsonPath('data.route.points.0.lat', 15.95)
            ->assertJsonPath('data.route.points.0.lng', 120.53)
            ->assertJsonPath('data.rider.lat', 15.95)
            ->assertJsonPath('data.rider.lng', 120.53);

        $profile->update(['current_lat' => 15.951, 'last_location_at' => now()]);
        DB::table('rider_locations')->where('delivery_id', $order->delivery->id)->update([
            'latitude' => 15.951,
            'recorded_at' => now(),
        ]);
        $this->travel(21)->seconds();
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.route.status', 'available')
            ->assertJsonPath('data.rider.lat', 15.951)
            ->assertJsonPath('data.route.origin.lat', 15.951);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            '120.53,15.951;120.54,15.96',
        ));
    }

    public function test_rider_delivery_returns_latest_assigned_gps_and_shared_road_route(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fake(['router.project-osrm.org/route/v1/driving/*' => Http::response($this->osrmSuccess(), 200)]);
        [$customer, $order] = $this->deliveryOrder();
        $delivery = $order->delivery;
        $assignedRider = $delivery->rider;
        $customer->update(['phone' => '09171234567', 'avatar' => 'avatars/customer-test.jpg']);
        $order->update(['delivery_fee' => 30, 'notes' => 'Please call on arrival.']);
        $category = Category::create(['name' => 'Rider details test category']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'base_price' => 40,
            'image' => 'products/rider-details-test.jpg',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Order Snapshot Product',
            'quantity' => 2,
            'unit_price' => 40,
            'total_price' => 80,
            'notes' => 'Less sugar, please.',
        ]);
        OrderItemOption::create([
            'order_item_id' => $item->id,
            'option_name' => 'Large',
            'extra_price' => 0,
        ]);
        $order->refresh();
        $otherRider = $this->user('rider');
        DB::table('rider_locations')->insert([
            'delivery_id' => $delivery->id,
            'rider_id' => $otherRider->id,
            'latitude' => 15.99,
            'longitude' => 120.59,
            'recorded_at' => now()->addSecond(),
        ]);

        $this->actingAs($assignedRider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.rider_location.lat', 15.95)
            ->assertJsonPath('data.rider_location.lng', 120.53)
            ->assertJsonPath('data.route.status', 'available')
            ->assertJsonPath('data.route.points.0.lat', 15.95)
            ->assertJsonPath('data.route.points.0.lng', 120.53)
            ->assertJsonPath('data.route.points.2.lat', 15.96)
            ->assertJsonPath('data.route.points.2.lng', 120.54)
            ->assertJsonPath('data.customer.name', $customer->name)
            ->assertJsonPath('data.customer.phone', '09171234567')
            ->assertJsonPath('data.customer.avatar_url', asset('storage/avatars/customer-test.jpg'))
            ->assertJsonPath('data.order.order_number', $order->order_number)
            ->assertJsonPath('data.order.created_at', $order->created_at->toIso8601String())
            ->assertJsonPath('data.order.subtotal', 100)
            ->assertJsonPath('data.order.delivery_fee', 30)
            ->assertJsonPath('data.order.total', 130)
            ->assertJsonPath('data.order.payment_method', 'cod')
            ->assertJsonPath('data.order.payment_status', 'unpaid')
            ->assertJsonPath('data.order.address', 'Customer delivery address')
            ->assertJsonPath('data.order.lat', $order->delivery_lat)
            ->assertJsonPath('data.order.lng', $order->delivery_lng)
            ->assertJsonPath('data.order.notes', 'Please call on arrival.')
            ->assertJsonPath('data.order.items.0.name', 'Order Snapshot Product')
            ->assertJsonPath('data.order.items.0.qty', 2)
            ->assertJsonPath('data.order.items.0.options.0', 'Large')
            ->assertJsonPath('data.order.items.0.unit_price', 40)
            ->assertJsonPath('data.order.items.0.total_price', 80)
            ->assertJsonPath('data.order.items.0.notes', 'Less sugar, please.')
            ->assertJsonPath('data.order.items.0.image_url', asset('storage/products/rider-details-test.jpg'));

        Http::assertSentCount(1);

        $order->update(['status' => 'delivered']);
        $this->getJson("/api/v1/rider/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.rider_location', null)
            ->assertJsonPath('data.route.status', 'finished')
            ->assertJsonPath('data.route.points', []);
        Http::assertSentCount(1);
    }
    public function test_rider_cannot_access_another_riders_delivery_details(): void
    {
        [, $order] = $this->deliveryOrder();
        $otherRider = $this->user('rider');

        $this->actingAs($otherRider, 'sanctum')
            ->getJson("/api/v1/rider/deliveries/{$order->delivery->id}")
            ->assertNotFound();
    }

    public function test_route_failure_returns_last_valid_route_and_a_safe_status_message(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fakeSequence('router.project-osrm.org/route/v1/driving/*')
            ->push($this->osrmSuccess(), 200)
            ->push(['code' => 'ServiceUnavailable'], 503);
        [$customer, $order, $profile] = $this->deliveryOrder();

        $first = $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk();
        $originalPoints = $first->json('data.route.points');

        $profile->update(['current_lat' => 15.952, 'last_location_at' => now()]);
        DB::table('rider_locations')->where('delivery_id', $order->delivery->id)->update([
            'latitude' => 15.952,
            'recorded_at' => now(),
        ]);
        $this->travel(21)->seconds();
        $this->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.route.status', 'stale')
            ->assertJsonPath('data.route.points', $originalPoints)
            ->assertJsonPath('data.route.error', 'Road routing is temporarily unavailable. Showing the last available route.');

        Http::assertSentCount(2);
    }

    public function test_customer_cannot_request_another_customers_tracking_route(): void
    {
        [$customer, $order] = $this->deliveryOrder();
        $otherCustomer = $this->user('customer');

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertNotFound();
    }

    public function test_stale_rider_gps_is_not_returned_or_used_as_a_route_origin(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fake();
        [$customer, $order] = $this->deliveryOrder();
        DB::table('rider_locations')->where('delivery_id', $order->delivery->id)->update([
            'recorded_at' => now()->subSeconds(31),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.rider.lat', null)
            ->assertJsonPath('data.rider.lng', null)
            ->assertJsonPath('data.route.status', 'unavailable')
            ->assertJsonPath('data.route.points', []);

        Http::assertNothingSent();
    }

    public function test_rider_location_endpoint_saves_the_active_delivery_gps_and_rejects_terminal_orders(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fake(['router.project-osrm.org/route/v1/driving/*' => Http::response($this->osrmSuccess(), 200)]);
        [$customer, $order] = $this->deliveryOrder();
        $rider = $order->delivery->rider;

        $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/rider/location', [
                'delivery_id' => $order->delivery->id,
                'latitude' => 15.955,
                'longitude' => 120.535,
                'speed' => 12,
            ])
            ->assertOk();

        $this->assertDatabaseHas('rider_locations', [
            'delivery_id' => $order->delivery->id,
            'rider_id' => $rider->id,
            'latitude' => 15.955,
            'longitude' => 120.535,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.rider.lat', 15.955)
            ->assertJsonPath('data.rider.lng', 120.535)
            ->assertJsonPath('data.route.origin.lat', 15.955)
            ->assertJsonPath('data.route.origin.lng', 120.535);

        $order->update(['status' => 'cancelled']);
        $this->actingAs($rider, 'sanctum')->postJson('/api/v1/rider/location', [
            'delivery_id' => $order->delivery->id,
            'latitude' => 15.956,
            'longitude' => 120.536,
        ])->assertNotFound();

        $this->assertDatabaseMissing('rider_locations', [
            'delivery_id' => $order->delivery->id,
            'rider_id' => $rider->id,
            'latitude' => 15.956,
            'longitude' => 120.536,
        ]);
    }

    public function test_final_orders_return_no_live_rider_or_osrm_route(): void
    {
        Cache::flush();
        config(['services.routing.provider' => 'osrm', 'services.routing.osrm_base_url' => 'https://router.project-osrm.org']);
        Http::fake();

        foreach (['delivered', 'cancelled', 'completed', 'rejected'] as $status) {
            [$customer, $order] = $this->deliveryOrder();
            $order->update(['status' => $status]);

            $this->actingAs($customer, 'sanctum')
                ->getJson("/api/v1/orders/{$order->id}/tracking")
                ->assertOk()
                ->assertJsonPath('data.order.status', $status)
                ->assertJsonPath('data.rider', null)
                ->assertJsonPath('data.route.status', 'finished')
                ->assertJsonPath('data.route.points', []);
        }

        foreach (['delivered', 'cancelled', 'completed', 'rejected'] as $deliveryStatus) {
            [$customer, $order] = $this->deliveryOrder();
            $order->delivery->update(['status' => $deliveryStatus]);

            $this->actingAs($customer, 'sanctum')
                ->getJson("/api/v1/orders/{$order->id}/tracking")
                ->assertOk()
                ->assertJsonPath('data.order.status', 'on_the_way')
                ->assertJsonPath('data.delivery_status', $deliveryStatus)
                ->assertJsonPath('data.rider', null)
                ->assertJsonPath('data.route.status', 'finished')
                ->assertJsonPath('data.route.points', []);
        }

        Http::assertNothingSent();
    }

    public function test_tracking_serializes_status_log_times_as_explicit_utc(): void
    {
        [$customer, $order] = $this->deliveryOrder();
        $order->update(['status' => 'cancelled']);
        DB::table('order_status_logs')->insert([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'changed_by' => $customer->id,
            'created_at' => '2026-10-29 11:41:00',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled')
            ->assertJsonPath('data.timeline.0.at', '2026-10-29T11:41:00+00:00')
            ->assertJsonPath('data.rider', null)
            ->assertJsonPath('data.route.points', []);
    }

    private function deliveryOrder(): array
    {
        $customer = $this->user('customer');
        $rider = $this->user('rider');
        $order = Order::create([
            'order_number' => 'KJM-ROUTE-' . Str::upper(Str::random(7)),
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
        $profile = RiderProfile::create([
            'user_id' => $rider->id,
            'current_lat' => 15.95,
            'current_lng' => 120.53,
            'last_location_at' => now(),
        ]);
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'on_the_way',
        ]);
        DB::table('rider_locations')->insert([
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'latitude' => 15.95,
            'longitude' => 120.53,
            'recorded_at' => now(),
        ]);

        return [$customer, $order, $profile];
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));
        return User::create([
            'name' => ucfirst($role) . ' Routing Test',
            'username' => $role . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function osrmSuccess(): array
    {
        return [
            'code' => 'Ok',
            'routes' => [[
                'distance' => 1800,
                'duration' => 420,
                'geometry' => ['coordinates' => [[120.53, 15.95], [120.535, 15.955], [120.54, 15.96]]],
            ]],
        ];
    }
}
