<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::with(['user:id,name', 'order:id,order_number', 'product:id,name'])
            ->when($request->rating, fn ($q, $v) => $q->where('rating', $v))
            ->latest()->paginate(10)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function toggle(Review $review)
    {
        $review->update(['is_visible' => !$review->is_visible]);
        $this->recalc($review);
        Activity::log('review', 'reviews', ($review->is_visible ? 'Ipinakita' : 'Itinago') . " ang review #{$review->id}");
        return back()->with('success', $review->is_visible ? 'Nakikita na ang review.' : 'Itinago ang review.');
    }

    public function reply(Request $request, Review $review)
    {
        $request->validate(['admin_reply' => 'nullable|string|max:1000']);
        $review->update(['admin_reply' => $request->admin_reply]);
        Activity::log('reply', 'reviews', "Sumagot sa review #{$review->id}");
        return back()->with('success', 'Na-save ang sagot.');
    }

    private function recalc(Review $review): void
    {
        if (!$review->product_id) return;
        $avg = Review::where('product_id', $review->product_id)->where('is_visible', true)->avg('rating');
        DB::table('products')->where('id', $review->product_id)->update(['avg_rating' => round($avg ?? 0, 2)]);
    }
}
