<?php
namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\OptionPresets;
use Illuminate\Database\Seeder;

class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        // [pangalan, presyo, category, description, may options?, iced?]
        $items = [
            ['Americano',          90,  'Hot Coffee',  'Espresso na hinaluan ng mainit na tubig.', true, false],
            ['Cafe Latte',         120, 'Hot Coffee',  'Espresso na may steamed milk.',            true, false],
            ['Cappuccino',         120, 'Hot Coffee',  'Espresso, steamed milk at makapal na foam.', true, false],
            ['Iced Latte',         140, 'Iced Coffee', 'Malamig na latte na may yelo.',            true, true],
            ['Spanish Latte',      150, 'Iced Coffee', 'Matamis na latte na may condensed milk.',  true, true],
            ['Caramel Macchiato',  160, 'Iced Coffee', 'Vanilla, gatas, espresso at caramel.',     true, true],
            ['Matcha Latte',       150, 'Non-Coffee',  'Matcha green tea na may gatas.',           true, true],
            ['Butter Croissant',   80,  'Pastry',      'Bagong lutong croissant.',                 false, false],
        ];

        foreach ($items as [$name, $price, $catName, $desc, $withOptions, $iced]) {
            $cat = Category::where('name', $catName)->first();
            if (!$cat) continue;

            $product = Product::updateOrCreate(
                ['name' => $name],
                ['category_id' => $cat->id, 'description' => $desc, 'base_price' => $price, 'is_available' => true]
            );

            if ($withOptions && !$product->optionGroups()->exists()) {
                OptionPresets::apply($product, $iced);
            }
        }
    }
}
