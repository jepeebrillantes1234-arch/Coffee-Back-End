<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    // GET /api/v1/categories
    public function categories()
    {
        $cats = Category::where('is_active', true)->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $cats, 'errors' => null]);
    }

    // GET /api/v1/products?category_id=&search=&page=
    public function products(Request $request)
    {
        $q = Product::with('category:id,name')
            ->where('is_available', true)
            ->whereHas('category', fn ($c) => $c->where('is_active', true));

        if ($request->filled('category_id')) $q->where('category_id', $request->category_id);
        if ($request->filled('search'))      $q->where('name', 'like', '%' . $request->search . '%');

        return response()->json([
            'success' => true, 'message' => 'OK',
            'data' => $q->orderBy('name')->paginate(20), 'errors' => null,
        ]);
    }

    // GET /api/v1/products/{id}  (kasama ang customization options)
    public function show(int $id)
    {
        $product = Product::with(['category:id,name', 'optionGroups.options' => fn ($o) => $o->where('is_available', true)])
            ->where('is_available', true)->findOrFail($id);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $product, 'errors' => null]);
    }

    // GET /api/v1/products/{id}/reviews
    public function reviews(int $id)
    {
        $rows = \Illuminate\Support\Facades\DB::table('reviews as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.product_id', $id)->where('r.is_visible', true)
            ->orderByDesc('r.id')->limit(20)
            ->get(['u.name', 'r.rating', 'r.comment', 'r.admin_reply', 'r.created_at']);

        $stats = \Illuminate\Support\Facades\DB::table('reviews')
            ->where('product_id', $id)->where('is_visible', true)
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')->first();

        return response()->json([
            'success' => true, 'message' => 'OK', 'errors' => null,
            'data' => [
                'count' => (int) $stats->c,
                'avg'   => round((float) $stats->a, 2),
                'items' => $rows->map(fn ($r) => [
                    'name'    => explode(' ', trim($r->name))[0],   // unang pangalan lang (privacy)
                    'rating'  => (int) $r->rating,
                    'comment' => $r->comment,
                    'reply'   => $r->admin_reply,
                    'date'    => \Carbon\Carbon::parse($r->created_at)->toDateString(),
                ])->values(),
            ],
        ]);
    }
}
