<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', ['s' => Settings::all()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_order_percent' => 'required|numeric|min:0|max:100',
            'first_order_max'     => 'required|numeric|min:0',
            'shop_lat'            => 'required|numeric|between:-90,90',
            'shop_lng'            => 'required|numeric|between:-180,180',
            'delivery_base_fee'   => 'required|numeric|min:0',
            'delivery_fee_per_km' => 'required|numeric|min:0',
            'delivery_radius_km'  => 'required|numeric|min:1',
            'auto_cancel_minutes' => 'required|integer|min:1|max:120',
        ]);
        $data['first_order_enabled']  = $request->boolean('first_order_enabled') ? '1' : '0';
        $data['allow_promo_stacking'] = $request->boolean('allow_promo_stacking') ? '1' : '0';

        foreach ($data as $k => $v) Settings::set($k, $v);
        Activity::log('update', 'settings', 'Ni-update ang First Order Discount / Delivery settings');

        return back()->with('success', 'Na-save ang settings.');
    }
}
