<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'support_name' => 'nullable|string|max:120',
            'support_phone' => 'nullable|string|max:30',
            'support_email' => 'nullable|email|max:255',
            'support_whatsapp' => 'nullable|string|max:40',
            'support_hours' => 'nullable|string|max:160',
            'membership_card_front_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'membership_card_back_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            // Keep existing admin forms/automation working; the legacy upload is the front.
            'membership_card_design_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);
        $data['first_order_enabled']  = $request->boolean('first_order_enabled') ? '1' : '0';
        $data['allow_promo_stacking'] = $request->boolean('allow_promo_stacking') ? '1' : '0';

        $frontUpload = $request->file('membership_card_front_image')
            ?? $request->file('membership_card_design_image');
        $backUpload = $request->file('membership_card_back_image');
        unset(
            $data['membership_card_front_image'],
            $data['membership_card_back_image'],
            $data['membership_card_design_image']
        );
        foreach ($data as $key => $value) Settings::set($key, $value);

        $previousFront = Settings::get('membership_card_front_design')
            ?: Settings::get('membership_card_design');
        $previousBack = Settings::get('membership_card_back_design');
        $frontPath = $frontUpload?->store('membership-cards', 'public') ?: $previousFront;
        $backPath = $backUpload?->store('membership-cards', 'public') ?: $previousBack;

        if ($frontPath) {
            Settings::set('membership_card_front_design', $frontPath);
            // Backwards-compatible field used by earlier app builds.
            Settings::set('membership_card_design', $frontPath);
        }
        if ($backPath) Settings::set('membership_card_back_design', $backPath);

        $active = $request->boolean('membership_card_design_active') && filled($frontPath);
        Settings::set('membership_card_design_active', $active ? '1' : '0');

        if ($frontUpload && $previousFront && $previousFront !== $frontPath) {
            Storage::disk('public')->delete($previousFront);
        }
        if ($backUpload && $previousBack && $previousBack !== $backPath) {
            Storage::disk('public')->delete($previousBack);
        }

        Activity::log('update', 'settings', 'Updated first order, delivery, and membership card settings');
        return back()->with('success', 'Settings saved.');
    }
}
