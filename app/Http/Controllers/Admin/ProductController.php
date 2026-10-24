<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('name')->paginate(15)->withQueryString();

        $categories = Category::orderBy('name')->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['is_available' => true]),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('products', 'public');

        $p = Product::create($data);
        $this->log('create', "Nagdagdag ng product: {$p->name}");

        return redirect()->route('admin.products.index')->with('success', 'Naidagdag ang product.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $this->log('update', "Ni-update ang product: {$product->name}");

        return redirect()->route('admin.products.index')->with('success', 'Na-update ang product.');
    }

    public function destroy(Product $product)
    {
        if ($product->image) Storage::disk('public')->delete($product->image);
        $this->log('delete', "Binura ang product: {$product->name}");
        $product->delete();

        return back()->with('success', 'Nabura ang product.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'base_price'  => 'required|numeric|min:0|max:99999',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0|max:99999',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'image'       => 'nullable|image|max:2048',
        ]);
        $data['is_available'] = $request->boolean('is_available');
        $data['discount_is_active'] = $request->boolean('discount_is_active') && !empty($data['discount_type']) && (float)($data['discount_value'] ?? 0) > 0;
        if (!$data['discount_is_active']) { $data['discount_type'] = null; $data['discount_value'] = 0; }
        unset($data['image']);
        return $data;
    }

    private function log(string $action, string $desc): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => auth()->id(), 'action' => $action, 'module' => 'products',
            'description' => $desc, 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
