<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Order;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Storage;
use App\Support\Settings;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** GET /api/v1/app-config */
    public function config(Request $request)
    {
        $isNewCustomer = ! Order::where('user_id', $request->user()->id)->exists();

        $settings = Settings::all();
        $qrphEnabled = config('shop.payment', 'cod') === 'online'
            && (config('paymongo.mode') === 'simulated' || filled(config('paymongo.secret')));
        $config = [
            'payment_method' => $qrphEnabled ? 'online' : 'cod',
            'payment_mode' => $qrphEnabled ? 'online' : 'cod',
            'qrph_enabled' => $qrphEnabled,
            'available_payment_methods' => $qrphEnabled ? ['cod', 'qrph'] : ['cod'],
            'first_order_enabled' => (bool) ((int) ($settings['first_order_enabled'] ?? 1)),
            'first_order_percent' => (float) ($settings['first_order_percent'] ?? 15),
            'first_order_max' => (float) ($settings['first_order_max'] ?? 100),
            'allow_promo_stacking' => (bool) ((int) ($settings['allow_promo_stacking'] ?? 0)),
            'delivery_base_fee' => (float) ($settings['delivery_base_fee'] ?? 30),
            'delivery_fee_per_km' => (float) ($settings['delivery_fee_per_km'] ?? 10),
            'delivery_radius_km' => (float) ($settings['delivery_radius_km'] ?? 8),
            'shop_lat' => isset($settings['shop_lat']) ? (float) $settings['shop_lat'] : null,
            'shop_lng' => isset($settings['shop_lng']) ? (float) $settings['shop_lng'] : null,
        ];

        $banners = Banner::live()
            ->where(function ($query) use ($isNewCustomer) {
                $query->where('audience', 'all');
                if ($isNewCustomer) {
                    $query->orWhere('audience', 'new_customers');
                }
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'settings' => $config, 'banners' => $banners,
                'payment_mode' => $config['payment_mode'],
                'first_order' => ['enabled' => $config['first_order_enabled'], 'eligible' => $isNewCustomer, 'percent' => $config['first_order_percent'], 'max' => $config['first_order_max']],
                'first_order_banner' => [
                    'title' => Settings::get('fo_title', 'First Order Discount'),
                    'headline' => str_replace('{percent}', (string) $config['first_order_percent'], Settings::get('fo_headline', 'GET {percent}% OFF')) ,
                    'subtitle' => Settings::get('fo_subtitle', 'on your order!'),
                    'image_url' => Settings::get('fo_image') ? Storage::disk('public')->url(Settings::get('fo_image')) : null,
                ],
                'promos' => PromoCode::live()->where('show_in_app', true)->orderBy('sort_order')->orderByDesc('id')->get(),
            ],
            'errors' => null,
        ]);
    }
}


