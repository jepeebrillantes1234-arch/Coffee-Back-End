<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Activity;

class ProductController extends Controller
{
    public function index()
    {
        $categories = Category::with(['products' => fn ($q) => $q->orderBy('name')])->orderBy('sort_order')->get();
        return view('staff.menu.index', compact('categories'));
    }

    // I-toggle ang Available <-> Sold out
    public function toggle(Product $product)
    {
        $product->update(['is_available' => !$product->is_available]);
        Activity::log('availability', 'products', "{$product->name}: " . ($product->is_available ? 'available' : 'sold out'));

        return back()->with('success', "{$product->name} ay " . ($product->is_available ? 'available na ulit.' : 'sold out na.'));
    }
}
