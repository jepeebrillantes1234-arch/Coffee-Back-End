<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Admin',    'admin@coffee.test',    '09170000001', 'admin'],
            ['Staff',    'staff@coffee.test',    '09170000002', 'staff'],
            ['Rider',    'rider@coffee.test',    '09170000003', 'rider'],
            ['Customer', 'customer@coffee.test', '09170000004', 'customer'],
        ];
        foreach ($users as [$name, $email, $phone, $role]) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'phone' => $phone, 'role' => $role,
                'password' => Hash::make('password'), 'status' => 'active',
            ]);
            if ($role === 'rider') {
                DB::table('rider_profiles')->updateOrInsert(['user_id' => $u->id],
                    ['vehicle_type' => 'Motorcycle', 'is_approved' => true,
                     'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $settings = [
            'first_order_enabled'  => '1',
            'first_order_percent'  => '15',
            'first_order_max'      => '100',
            'allow_promo_stacking' => '0',
            'shop_lat'             => '15.0000000',  // PALITAN: lokasyon ng shop
            'shop_lng'             => '120.0000000', // PALITAN
            'delivery_base_fee'    => '30',
            'delivery_fee_per_km'  => '10',
            'delivery_radius_km'   => '8',
            'auto_cancel_minutes'  => '10',
        ];
        foreach ($settings as $k => $v) {
            DB::table('settings')->updateOrInsert(['key' => $k],
                ['value' => $v, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['Hot Coffee','Iced Coffee','Non-Coffee','Pastry'] as $i => $c) {
            DB::table('categories')->updateOrInsert(['name' => $c],
                ['sort_order' => $i, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
