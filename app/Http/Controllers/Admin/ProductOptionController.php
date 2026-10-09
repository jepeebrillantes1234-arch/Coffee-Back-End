<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Support\OptionPresets;
use Illuminate\Http\Request;

class ProductOptionController extends Controller
{
    public function index(Product $product)
    {
        $product->load('optionGroups.options');
        return view('admin.products.options', compact('product'));
    }

    public function preset(Request $request, Product $product)
    {
        if ($product->optionGroups()->exists()) {
            return back()->with('error', 'May options na ang product. Burahin muna ang mga group kung gusto mong gamitin ang preset.');
        }
        OptionPresets::apply($product, $request->boolean('iced'));
        return back()->with('success', 'Nailagay ang standard coffee options.');
    }

    public function storeGroup(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'max_select' => 'nullable|integer|min:1|max:10',
        ]);

        $multiple = $request->boolean('is_multiple');
        $product->optionGroups()->create([
            'name'        => $data['name'],
            'is_required' => $request->boolean('is_required'),
            'is_multiple' => $multiple,
            'max_select'  => $multiple ? ($data['max_select'] ?? 3) : 1,
        ]);

        return back()->with('success', 'Naidagdag ang group.');
    }

    public function destroyGroup(ProductOptionGroup $group)
    {
        $group->delete(); // cascade sa options
        return back()->with('success', 'Nabura ang group.');
    }

    public function storeOption(Request $request, ProductOptionGroup $group)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'extra_price' => 'nullable|numeric|min:0|max:9999',
        ]);
        $group->options()->create([
            'name' => $data['name'], 'extra_price' => $data['extra_price'] ?? 0,
        ]);
        return back()->with('success', 'Naidagdag ang option.');
    }

    public function destroyOption(ProductOption $option)
    {
        $option->delete();
        return back()->with('success', 'Nabura ang option.');
    }
}
