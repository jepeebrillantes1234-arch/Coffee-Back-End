<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
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
