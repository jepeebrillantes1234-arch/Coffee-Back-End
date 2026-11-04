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

class AdminGpsMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_only_returns_approved_online_riders_with_active_delivery_and_fresh_delivery_gps(): void
    {
        Cache::flush();
        config([
            'services.routing.provider' => 'osrm',
            'services.routing.osrm_base_url' => 'https://router.project-osrm.org',
        ]);
        Http::fake(['router.project-osrm.org/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 1800,
                'duration' => 420,
                'geometry' => ['coordinates' => [[120.53, 15.95], [120.54, 15.96]]],
            ]],
        ], 200)]);

        $offline = $this->createDelivery('offline', true, 'on_the_way');
        RiderProfile::where('user_id', $offline['rider']->id)->update(['is_available' => false]);
        $unapproved = $this->createDelivery('unapproved', true, 'on_the_way');
        RiderProfile::where('user_id', $unapproved['rider']->id)->update(['is_approved' => false]);
        $this->createRiderWithoutDelivery();
        $this->createDelivery('stale', false, 'on_the_way');
        $this->createDelivery('delivered', true, 'delivered');
        $this->createDelivery('cancelled', true, 'cancelled');
        $fresh = $this->createDelivery('fresh', true, 'on_the_way');

        DB::table('rider_locations')->where('delivery_id', $fresh['delivery']->id)->update([
            'latitude' => 15.95,
            'longitude' => 120.53,
        ]);

        $this->actingAs($this->user('admin'))
            ->getJson(route('admin.gps.data'))
            ->assertOk()
            ->assertJsonCount(1, 'riders')
            ->assertJsonPath('riders.0.id', $fresh['delivery']->id)
            ->assertJsonPath('riders.0.name', $fresh['rider']->name)
            ->assertJsonPath('riders.0.avatar_url', asset('storage/riders/fresh.jpg'))
            ->assertJsonPath('riders.0.lat', 15.95)
            ->assertJsonPath('riders.0.lng', 120.53)
            ->assertJsonPath('riders.0.order', $fresh['order']->order_number)
            ->assertJsonPath('riders.0.status', 'on_the_way')
            ->assertJsonPath('riders.0.customer.name', $fresh['customer']->name)
            ->assertJsonPath('riders.0.customer.avatar_url', asset('storage/customers/fresh.jpg'))
            ->assertJsonPath('riders.0.destination.lat', 15.96)
            ->assertJsonPath('riders.0.destination.lng', 120.54)
            ->assertJsonPath('riders.0.destination.address', 'Fresh customer delivery address')
            ->assertJsonPath('riders.0.route.status', 'available')
            ->assertJsonPath('riders.0.route.distance_meters', 1800);

        Http::assertSentCount(1);
    }

    public function test_map_keeps_separate_active_deliveries_for_the_same_rider(): void
    {
        Cache::flush();
        config([
            'services.routing.provider' => 'osrm',
            'services.routing.osrm_base_url' => 'https://router.project-osrm.org',
        ]);
        Http::fake(['router.project-osrm.org/route/v1/driving/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 1000,
                'duration' => 300,
                'geometry' => ['coordinates' => [[120.53, 15.95], [120.54, 15.96]]],
            ]],
        ], 200)]);

        $first = $this->createDelivery('first', true, 'accepted');
        $second = $this->createDelivery('second', true, 'picked_up', $first['rider']);

        $this->actingAs($this->user('admin'))
            ->getJson(route('admin.gps.data'))
            ->assertOk()
            ->assertJsonCount(2, 'riders')
            ->assertJsonFragment(['order' => $first['order']->order_number])
            ->assertJsonFragment(['order' => $second['order']->order_number]);
    }

    public function test_admin_gps_view_keeps_reverb_updates_and_five_second_polling_fallback(): void
    {
        $view = file_get_contents(resource_path('views/admin/gps/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('setInterval(refreshRiders,5000)', $view);
        $this->assertStringContainsString('cachedRiders=Array.isArray(json.riders)', $view);
        $this->assertStringContainsString('age<=120000', $view);
        $this->assertStringContainsString("window.addEventListener('live-update'", $view);
    }
    private function createDelivery(string $suffix, bool $fresh, string $status, ?User $rider = null): array
    {
        $customer = $this->user('customer');
        $customer->update(['avatar' => 'customers/' . $suffix . '.jpg']);
        $rider ??= $this->user('rider');
        $rider->update(['avatar' => 'riders/' . $suffix . '.jpg']);
        if (!$rider->riderProfile()->exists()) {
            RiderProfile::create([
                'user_id' => $rider->id,
                'is_approved' => true,
                'is_available' => true,
            ]);
        }
        $orderStatus = $status === 'delivered' ? 'delivered' : ($status === 'cancelled' ? 'cancelled' : 'on_the_way');
        $order = Order::create([
            'order_number' => 'KJM-GPS-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'delivery',
            'status' => $orderStatus,
            'subtotal' => 100,
            'total' => 130,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'delivery_address' => ucfirst($suffix) . ' customer delivery address',
            'delivery_lat' => 15.96,
            'delivery_lng' => 120.54,
        ]);
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => $status === 'cancelled' ? 'on_the_way' : $status,
        ]);
        DB::table('rider_locations')->insert([
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'latitude' => 15.8,
            'longitude' => 120.4,
            'recorded_at' => $fresh ? now() : now()->subMinutes(2)->subSecond(),
        ]);

        return compact('customer', 'rider', 'order', 'delivery');
    }

    private function createRiderWithoutDelivery(): User
    {
        $rider = $this->user('rider');
        RiderProfile::create([
            'user_id' => $rider->id,
            'is_approved' => true,
            'is_available' => true,
        ]);

        return $rider;
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));

        return User::create([
            'name' => ucfirst($role) . ' GPS ' . $suffix,
            'username' => $role . $suffix,
            'email' => $suffix . '@gps.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}