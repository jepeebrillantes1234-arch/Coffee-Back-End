<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gumagawa ng 16 demo orders (may items, payments, deliveries, reviews, GPS ng rider)
 * para may laman ang Dashboard, Orders, Reports, GPS Monitor, atbp.
 * Kailangan muna: DatabaseSeeder at DemoProductsSeeder.
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('orders')->where('order_number', 'like', 'DEMO-%')->exists()) {
            $this->command?->warn('May demo orders na. Nilaktawan.');
            return;
        }

        $customer = DB::table('users')->where('email', 'customer@coffee.test')->first();
        $rider    = DB::table('users')->where('email', 'rider@coffee.test')->first();
        $staff    = DB::table('users')->where('email', 'staff@coffee.test')->first();
        $products = DB::table('products')->get();

        if (!$customer || !$rider || !$staff || $products->isEmpty()) {
            $this->command?->warn('Patakbuhin muna ang DatabaseSeeder at DemoProductsSeeder.');
            return;
        }

        $shopLat = (float) (DB::table('settings')->where('key', 'shop_lat')->value('value') ?: 14.5995);
        $shopLng = (float) (DB::table('settings')->where('key', 'shop_lng')->value('value') ?: 120.9842);

        $types    = ['delivery', 'pickup', 'walkin'];
        $statuses = ['completed', 'completed', 'completed', 'delivered', 'on_the_way', 'preparing', 'pending', 'cancelled'];
        $comments = ['Ang sarap ng kape!', 'Mabilis ang delivery.', 'Okay naman, medyo matamis.', 'Babalik ako ulit!'];

        for ($i = 1; $i <= 16; $i++) {
            $created = now()->subDays($i % 7)->subMinutes(mt_rand(10, 600));
            $type    = $types[$i % 3];
            $status  = $statuses[$i % count($statuses)];

            if ($type === 'walkin') $status = 'completed';
            if ($type !== 'delivery' && in_array($status, ['on_the_way', 'delivered'], true)) $status = 'completed';
            $isDelivery = $type === 'delivery';

            // Items
            $picked   = $products->shuffle()->take(min(mt_rand(1, 3), $products->count()));
            $lines    = [];
            $subtotal = 0;
            foreach ($picked as $p) {
                $qty = mt_rand(1, 2);
                $tot = $qty * (float) $p->base_price;
                $subtotal += $tot;
                $lines[] = [$p, $qty, $tot];
            }

            $discount = $i % 4 === 0 ? round($subtotal * 0.10, 2) : 0;
            $fee      = $isDelivery ? 40 : 0;
            $total    = $subtotal - $discount + $fee;
            $paid     = in_array($status, ['completed', 'delivered'], true);
            $method   = $isDelivery ? 'cod' : ($type === 'pickup' ? 'gcash' : 'cash');

            $orderId = DB::table('orders')->insertGetId([
                'order_number'     => 'DEMO-' . $created->format('Ymd') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'user_id'          => $type === 'walkin' ? null : $customer->id,
                'staff_id'         => $type === 'walkin' ? $staff->id : null,
                'order_type'       => $type,
                'status'           => $status,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'delivery_fee'     => $fee,
                'total'            => $total,
                'is_first_order'   => $i === 1,
                'payment_method'   => $method,
                'payment_status'   => $paid ? 'paid' : 'unpaid',
                'delivery_address' => $isDelivery ? 'Blk 5 Lot 2, Sample St., Sample City' : null,
                'delivery_lat'     => $isDelivery ? $shopLat + 0.010 : null,
                'delivery_lng'     => $isDelivery ? $shopLng + 0.008 : null,
                'reject_reason'    => $status === 'cancelled' ? 'Demo: nag-cancel ang customer' : null,
                'created_at'       => $created,
                'updated_at'       => $created,
            ]);

            foreach ($lines as [$p, $qty, $tot]) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId, 'product_id' => $p->id, 'product_name' => $p->name,
                    'quantity' => $qty, 'unit_price' => $p->base_price, 'total_price' => $tot,
                ]);
            }

            DB::table('order_status_logs')->insert([
                ['order_id' => $orderId, 'status' => 'pending', 'changed_by' => null, 'created_at' => $created],
                ['order_id' => $orderId, 'status' => $status, 'changed_by' => $staff->id, 'created_at' => (clone $created)->addMinutes(5)],
            ]);

            if ($paid) {
                DB::table('payments')->insert([
                    'order_id' => $orderId, 'method' => $method, 'amount' => $total, 'status' => 'paid',
                    'paid_at' => $created, 'created_at' => $created, 'updated_at' => $created,
                ]);
            }

            if ($isDelivery && in_array($status, ['completed', 'delivered', 'on_the_way'], true)) {
                $onTheWay = $status === 'on_the_way';
                $deliveryId = DB::table('deliveries')->insertGetId([
                    'order_id' => $orderId, 'rider_id' => $rider->id,
                    'status' => $onTheWay ? 'on_the_way' : 'delivered',
                    'distance_km' => mt_rand(15, 60) / 10,
                    'assigned_at' => $created, 'accepted_at' => $created, 'picked_up_at' => $created,
                    'delivered_at' => $onTheWay ? null : (clone $created)->addMinutes(30),
                    'created_at' => $created, 'updated_at' => $created,
                ]);
                if ($onTheWay) {
                    DB::table('rider_locations')->insert([
                        'delivery_id' => $deliveryId, 'rider_id' => $rider->id,
                        'latitude' => $shopLat + 0.004, 'longitude' => $shopLng + 0.003,
                        'speed' => 25, 'recorded_at' => now(),
                    ]);
                }
            }

            if ($status === 'completed' && $type !== 'walkin' && $i % 2 === 0) {
                DB::table('reviews')->insert([
                    'order_id' => $orderId, 'user_id' => $customer->id, 'product_id' => $lines[0][0]->id,
                    'rating' => mt_rand(3, 5), 'comment' => $comments[$i % count($comments)],
                    'is_visible' => true, 'created_at' => $created, 'updated_at' => $created,
                ]);
            }
        }

        // Rider online + may GPS position (para sa GPS Monitor)
        DB::table('rider_profiles')->where('user_id', $rider->id)->update([
            'is_available' => true, 'current_lat' => $shopLat + 0.004, 'current_lng' => $shopLng + 0.003,
            'last_location_at' => now(),
        ]);
    }
}
