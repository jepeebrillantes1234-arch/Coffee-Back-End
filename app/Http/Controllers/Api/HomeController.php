<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\HomePromo;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
            && (config('paymongo.mode') === 'simulated' || filled(config('paymongo.secret')))
            && (bool) ((int) Settings::get('payment_qrph_enabled', 1));
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

        $banners = $this->liveHomeBanners($isNewCustomer);

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
                'home_promos' => Schema::hasTable('home_promos') ? HomePromo::live()->with(['products.category'])->orderBy('sort_order')->orderByDesc('id')->get()->filter(fn ($promo) => $promo->products->isNotEmpty() && $promo->products->sum(fn ($p) => (float)$p->base_price * (int)$p->pivot->quantity) > (float)$promo->price)->map(fn ($promo) => $this->homePromo($promo))->values() : [],
                'discounted_products' => Product::query()
                    ->where('is_available', true)
                    ->where('discount_is_active', true)
                    ->whereNotNull('discount_type')
                    ->where('discount_value', '>', 0)
                    ->where(fn ($query) => $query->whereNull('discount_starts_at')->orWhere('discount_starts_at', '<=', now()))
                    ->where(fn ($query) => $query->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>=', now()))
                    ->orderBy('name')
                    ->get(),
                'best_sellers_enabled' => (bool)((int)Settings::get('home_best_sellers_enabled', 1)),
                'best_sellers' => (bool)((int)Settings::get('home_best_sellers_enabled', 1)) ? $this->bestSellers() : [],
            ],
            'errors' => null,
        ]);
    }

    /** Public, whitelisted Home banners. Promo codes stay on the Promotions API. */
    public function banners()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $this->liveHomeBanners(false),
            'errors' => null,
        ]);
    }

    private function liveHomeBanners(bool $isNewCustomer): array
    {
        return Banner::live()
            ->where(function ($query) use ($isNewCustomer) {
                $query->where('audience', 'all');
                if ($isNewCustomer) {
                    $query->orWhere('audience', 'new_customers');
                }
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'source_type' => 'banner',
                'title' => $banner->title,
                'headline' => $banner->headline,
                'description' => $banner->description,
                'image_url' => $banner->image_url,
                'banner_type' => $banner->banner_type,
                'destination' => $banner->destination,
                'destination_id' => $banner->destination_id,
                'button_text' => $banner->button_text,
                'sort_order' => (int) $banner->sort_order,
                'is_active' => true,
            ])
            ->values()
            ->all();
    }
    /** Public promo list so the Promos screen does not depend on home merchandising data. */
    public function promos()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => PromoCode::live()->where('show_in_app', true)
                ->with('product:id,name,image,base_price,is_available')
                ->orderBy('sort_order')->orderByDesc('id')->get(),
            'errors' => null,
        ]);
    }

    private function homePromo(HomePromo $promo): array
    {
        $products = $promo->products->map(fn ($p) => ['id'=>$p->id,'name'=>$p->name,'quantity'=>(int)$p->pivot->quantity,'base_price'=>(float)$p->base_price,'image_url'=>$p->image_url]);
        $original = (float)$promo->products->sum(fn ($p) => (float)$p->base_price * (int)$p->pivot->quantity);
        return ['id'=>$promo->id,'name'=>$promo->name,'description'=>$promo->description,'price'=>(float)$promo->price,'image_url'=>$promo->image_url,'starts_at'=>$promo->starts_at,'ends_at'=>$promo->ends_at,'products'=>$products,'original_total'=>round($original,2),'savings'=>round(max(0,$original-(float)$promo->price),2),'discount_percent'=>$original>0?round(max(0,$original-(float)$promo->price)/$original*100,1):0];
    }

    private function bestSellers(): array
    {
        // Aggregate sales separately so MySQL strict GROUP BY does not require
        // every products column to be listed in GROUP BY.
        $sales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.product_id')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->whereNotIn('orders.status', ['awaiting_payment', 'cancelled', 'rejected'])
            ->where('orders.payment_status', '!=', 'refunded')
            ->where(fn ($query) => $query->where('orders.payment_status', 'paid')->orWhereIn('orders.payment_method', ['cod', 'cash']))
            ->groupBy('order_items.product_id');

        return Product::query()
            ->joinSub($sales, 'sales', fn ($join) => $join->on('sales.product_id', '=', 'products.id'))
            ->where('products.is_available', true)
            ->orderByDesc('sales.units_sold')
            ->limit(8)
            ->get(['products.*', 'sales.units_sold'])
            ->values()
            ->all();
    }
}


