<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gumagawa ng bagong "naka-assign" na delivery para sa rider@coffee.test.
 * Patakbuhin ulit tuwing gusto mo ng bagong job:
 *   php artisan db:seed --class=DemoRiderJobSeeder
 */
class DemoRiderJobSeeder extends Seeder
{
    public function run(): void
    {
        $customer = DB::table('users')->where('email', 'customer@coffee.test')->first();
        $rider    = DB::table('users')->where('email', 'rider@coffee.test')->first();
        $product  = DB::table('products')->first();

        if (!$customer || !$rider || !$product) {
            $this->command?->warn('Patakbuhin muna ang DatabaseSeeder at DemoProductsSeeder.');
            return;
        }

        $shopLat = (float) (DB::table('settings')->where('key', 'shop_lat')->value('value') ?: 14.5995);
        $shopLng = (float) (DB::table('settings')->where('key', 'shop_lng')->value('value') ?: 120.9842);
        $now = now();

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'JOB-' . $now->format('mdHis') . '-' . mt_rand(10, 99),
            'user_id' => $customer->id, 'order_type' => 'delivery', 'status' => 'rider_assigned',
            'subtotal' => $product->base_price * 2, 'discount' => 0, 'delivery_fee' => 40,
            'total' => $product->base_price * 2 + 40,
            'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'delivery_address' => 'Bahay ng customer (demo), Sample St.',
            'delivery_lat' => $shopLat + 0.012, 'delivery_lng' => $shopLng + 0.009,
            'notes' => 'Paki-doorbell po', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId, 'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 2, 'unit_price' => $product->base_price, 'total_price' => $product->base_price * 2,
        ]);

        DB::table('deliveries')->insert([
            'order_id' => $orderId, 'rider_id' => $rider->id, 'status' => 'assigned',
            'distance_km' => 2.1, 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('rider_profiles')->where('user_id', $rider->id)->update(['is_approved' => true]);

        $this->command?->info('Nagawa ang bagong delivery job para kay rider@coffee.test');
    }
}
