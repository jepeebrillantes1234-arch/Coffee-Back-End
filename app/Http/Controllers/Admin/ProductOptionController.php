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
            return back()->with('error', 'This product already has options. Delete the existing groups before applying the preset.');
        }
        OptionPresets::apply($product, $request->boolean('iced'));
        return back()->with('success', 'Standard coffee options applied successfully.');
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

        return back()->with('success', 'Option group added successfully.');
    }

    public function destroyGroup(ProductOptionGroup $group)
    {
        $group->delete(); // cascade deletes options
        return back()->with('success', 'Option group deleted successfully.');
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
        return back()->with('success', 'Option added successfully.');
    }

    public function destroyOption(ProductOption $option)
    {
        $option->delete();
        return back()->with('success', 'Option deleted successfully.');
    }
}
