<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\OrderTrackingRouter;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GpsMonitorController extends Controller
{
    public function index()
    {
        $shop = [
            'lat' => (float) Settings::get('shop_lat', 14.5995),
            'lng' => (float) Settings::get('shop_lng', 120.9842),
        ];

        return view('admin.gps.index', compact('shop'));
    }

    public function data()
    {
        $activeDeliveryStatuses = ['assigned', 'accepted', 'picked_up', 'on_the_way'];
        $terminalOrderStatuses = ['delivered', 'completed', 'cancelled', 'rejected'];
        $cutoff = now()->subMinutes(2);

        $deliveries = Delivery::query()
            ->whereIn('status', $activeDeliveryStatuses)
            ->whereHas('rider', function ($query) {
                $query->where('role', 'rider')
                    ->where('status', 'active')
                    ->whereHas('riderProfile', fn ($profile) => $profile
                        ->where('is_approved', true)
                        ->where('is_available', true));
            })
            ->whereHas('order', fn ($query) => $query
                ->where('order_type', 'delivery')
                ->whereNotIn('status', $terminalOrderStatuses))
            ->with(['rider.riderProfile', 'order.user'])
            ->get();

        $router = app(OrderTrackingRouter::class);
        $riders = $deliveries->map(function (Delivery $delivery) use ($cutoff, $router) {
            $rider = $delivery->rider;
            $order = $delivery->order;
            $location = DB::table('rider_locations')
                ->where('delivery_id', $delivery->id)
                ->where('rider_id', $delivery->rider_id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->first();

            if (!$location || Carbon::parse($location->recorded_at)->lt($cutoff)) {
                return null;
            }

            $origin = ['lat' => (float) $location->latitude, 'lng' => (float) $location->longitude];
            $hasDestination = $order->delivery_lat !== null && $order->delivery_lng !== null;
            $destination = $hasDestination
                ? ['lat' => (float) $order->delivery_lat, 'lng' => (float) $order->delivery_lng]
                : null;
            $route = $hasDestination
                ? $router->route((int) $order->id, $origin, $destination)
                : ['status' => 'unavailable', 'error' => 'Customer destination coordinates are unavailable.', 'points' => []];

            return [
                'id' => (int) $delivery->id,
                'rider_id' => (int) $rider->id,
                'name' => $rider->name,
                'avatar_url' => $this->avatarUrl($rider->avatar),
                'lat' => (float) $location->latitude,
                'lng' => (float) $location->longitude,
                'available' => true,
                'updated' => Carbon::parse($location->recorded_at)->diffForHumans(),
                'updated_at' => Carbon::parse($location->recorded_at)->toIso8601String(),
                'order' => $order->order_number,
                'status' => $delivery->status,
                'customer' => [
                    'name' => $order->user?->name,
                    'avatar_url' => $this->avatarUrl($order->user?->avatar),
                ],
                'destination' => [
                    'lat' => $destination['lat'] ?? null,
                    'lng' => $destination['lng'] ?? null,
                    'address' => $order->delivery_address,
                ],
                'route' => $route,
            ];
        })->filter()->values();

        return response()->json(['riders' => $riders]);
    }

    private function avatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(public/|storage/)#', '', $path);

        return asset('storage/' . $path);
    }
}