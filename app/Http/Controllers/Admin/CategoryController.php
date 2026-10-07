<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('categories', 'public');

        $cat = Category::create($data);
        $this->log('create', "Nagdagdag ng category: {$cat->name}");

        return redirect()->route('admin.categories.index')->with('success', 'Naidagdag ang category.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($category->image) Storage::disk('public')->delete($category->image);
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);
        $this->log('update', "Ni-update ang category: {$category->name}");

        return redirect()->route('admin.categories.index')->with('success', 'Na-update ang category.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'May mga product pa ang category na ito. Ilipat o burahin muna ang mga iyon.');
        }
        if ($category->image) Storage::disk('public')->delete($category->image);
        $this->log('delete', "Binura ang category: {$category->name}");
        $category->delete();

        return back()->with('success', 'Nabura ang category.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'image'      => 'nullable|image|max:2048',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active');
        unset($data['image']);
        return $data;
    }

    private function log(string $action, string $desc): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => auth()->id(), 'action' => $action, 'module' => 'categories',
            'description' => $desc, 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
