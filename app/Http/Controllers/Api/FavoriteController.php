<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        $ids = DB::table('favorites')->where('user_id', $request->user()->id)->pluck('product_id');
        return $this->ok(Product::with('category:id,name')->whereIn('id', $ids)->where('is_available', true)->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);
        DB::table('favorites')->updateOrInsert(
            ['user_id' => $request->user()->id, 'product_id' => $data['product_id']],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return $this->ok(null, 'Naidagdag sa favorites', 201);
    }

    public function destroy(Request $request, int $productId)
    {
        DB::table('favorites')->where('user_id', $request->user()->id)->where('product_id', $productId)->delete();
        return $this->ok(null, 'Naalis sa favorites');
    }
}
