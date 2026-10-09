<?php
namespace App\Support;

use App\Models\Product;

class OptionPresets
{
    /** Standard na coffee customization. $iced = kasama ang Ice level. */
    public static function apply(Product $product, bool $iced = true): void
    {
        $groups = [
            ['Size', true, false, 1, [['Small', 0], ['Medium', 20], ['Large', 40]]],
            ['Sugar Level', true, false, 1, [['0%', 0], ['25%', 0], ['50%', 0], ['75%', 0], ['100%', 0]]],
        ];

        if ($iced) {
            $groups[] = ['Ice Level', true, false, 1, [['No Ice', 0], ['Less Ice', 0], ['Normal Ice', 0]]];
        }

        $groups[] = ['Milk', false, false, 1, [['Regular', 0], ['Oat Milk', 30], ['Almond Milk', 30]]];
        $groups[] = ['Add-ons', false, true, 3, [['Extra Shot', 30], ['Whipped Cream', 20], ['Caramel Drizzle', 20]]];

        foreach ($groups as [$name, $required, $multiple, $max, $options]) {
            $group = $product->optionGroups()->create([
                'name' => $name, 'is_required' => $required,
                'is_multiple' => $multiple, 'max_select' => $max,
            ]);
            foreach ($options as [$optName, $price]) {
                $group->options()->create(['name' => $optName, 'extra_price' => $price]);
            }
        }
    }
}
