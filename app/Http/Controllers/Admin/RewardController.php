<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RewardController extends Controller
{
    public function index(Request $request)
    {
        $rewards = Reward::with('product:id,name')->withCount('redemptions')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . trim($request->q) . '%'))
            ->when($request->has('active') && $request->active !== '', fn ($q) => $q->where('is_active', (bool) $request->active))
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.rewards.index', compact('rewards'));
    }

    public function create() { $products = Product::orderBy('name')->get(['id', 'name']); return view('admin.rewards.form', ['reward' => new Reward(), 'products' => $products]); }
    public function edit(Reward $reward) { $products = Product::orderBy('name')->get(['id', 'name']); return view('admin.rewards.form', compact('reward', 'products')); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('rewards', 'public');
        Reward::create($data);
        return redirect()->route('admin.rewards.index')->with('success', 'Reward created.');
    }

    public function update(Request $request, Reward $reward)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($reward->image) Storage::disk('public')->delete($reward->image);
            $data['image'] = $request->file('image')->store('rewards', 'public');
        }
        $reward->update($data);
        return redirect()->route('admin.rewards.index')->with('success', 'Reward updated.');
    }

    public function toggle(Reward $reward)
    {
        $reward->update(['is_active' => !$reward->is_active]);
        return back()->with('success', 'Reward status updated.');
    }

    public function destroy(Reward $reward)
    {
        if ($reward->redemptions()->exists()) return back()->with('error', 'This reward has redemption history and cannot be deleted. Deactivate it instead.');
        if ($reward->image) Storage::disk('public')->delete($reward->image);
        $reward->delete();
        return back()->with('success', 'Reward deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'description' => 'nullable|string|max:4000',
            'reward_type' => 'required|in:free_product,discount,other',
            'product_id' => 'nullable|required_if:reward_type,free_product|exists:products,id',
            'discount_amount' => 'nullable|required_if:reward_type,discount|numeric|min:0.01|max:100000',
            'points_cost' => 'required|integer|min:1|max:1000000',
            'stock' => 'nullable|integer|min:0|max:1000000', 'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean', 'image' => 'nullable|image|max:5120',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['reward_type'] !== 'discount') $data['discount_amount'] = null;
        if ($data['reward_type'] !== 'free_product') $data['product_id'] = null;
        return $data;
    }
}