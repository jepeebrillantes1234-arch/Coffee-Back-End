<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\User;
use App\Support\Settings;

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

    // Tinatawag ng mapa tuwing 5 segundo
    public function data()
    {
        $riders = User::where('role', 'rider')->where('status', 'active')
            ->whereHas('riderProfile', fn ($q) => $q->whereNotNull('current_lat'))
            ->with('riderProfile')->get()
            ->map(function ($u) {
                $delivery = Delivery::with('order:id,order_number')
                    ->where('rider_id', $u->id)
                    ->whereIn('status', ['accepted', 'picked_up', 'on_the_way'])
                    ->latest()->first();

                return [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'lat'       => (float) $u->riderProfile->current_lat,
                    'lng'       => (float) $u->riderProfile->current_lng,
                    'available' => (bool) $u->riderProfile->is_available,
                    'updated'   => optional($u->riderProfile->last_location_at)->diffForHumans(),
                    'order'     => optional(optional($delivery)->order)->order_number,
                    'status'    => optional($delivery)->status,
                ];
            })->values();

        return response()->json(['riders' => $riders]);
    }
}
