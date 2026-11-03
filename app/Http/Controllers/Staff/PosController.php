<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Membership;
use App\Models\StaffShift;
use App\Services\CheckoutService;
use App\Services\MembershipPricing;
use App\Support\Activity;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function verifyMembership(Request $request, MembershipPricing $pricing)
    {
        $data = $request->validate([
            'card_number' => 'required|string|max:24',
        ]);

        $membership = Membership::with('user:id,name')
            ->where('member_id', strtoupper(trim($data['card_number'])))
            ->first();

        if (!$membership || !$pricing->eligible($membership)) {
            return response()->json([
                'success' => false,
                'message' => 'This membership could not be verified or is not eligible for a discount.',
            ], 422);
        }

        $configuration = $pricing->configuration();

        return response()->json([
            'success' => true,
            'data' => [
                'card_number' => $membership->member_id,
                'member_name' => $membership->user?->name,
                'status' => 'eligible',
                'discount_enabled' => $configuration['discount_enabled'] && $configuration['discount_percent'] > 0,
                'discount_percent' => $configuration['discount_enabled'] ? $configuration['discount_percent'] : 0,
            ],
        ]);
    }

    public function index()
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'image'])
            ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'image' => $category->image_url])
            ->values();

        $products = \App\Models\Product::with(['optionGroups.options' => fn ($q) => $q->where('is_available', true)])
            ->where('is_available', true)
            ->whereIn('category_id', $categories->pluck('id'))
            ->orderBy('name')->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'category_id' => $p->category_id,
                'name'        => $p->name,
                'price'       => (float) $p->base_price,
                'image'       => $p->image ? asset('storage/' . $p->image) : null,
                'groups'      => $p->optionGroups->map(fn ($g) => [
                    'id' => $g->id, 'name' => $g->name, 'required' => (bool) $g->is_required,
                    'multiple' => (bool) $g->is_multiple, 'max' => (int) $g->max_select,
                    'options' => $g->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price' => (float) $o->extra_price])->values(),
                ])->values(),
            ])->values();

        return view('staff.pos.index', ['catalog' => ['categories' => $categories, 'products' => $products]]);
    }

    public function store(Request $request, CheckoutService $svc)
    {
        $data = $request->validate([
            'items'                => 'required|array|min:1|max:50',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1|max:50',
            'items.*.option_ids'   => 'nullable|array',
            'items.*.option_ids.*' => 'integer',
            'items.*.notes'        => 'nullable|string|max:255',
            'cash'                 => 'required|numeric|min:0',
            'customer_name'        => 'nullable|string|max:60',
        ]);

        $payload = ['items' => $data['items']];
        if (!empty($data['customer_name'])) $payload['notes'] = 'Customer: ' . $data['customer_name'];

        $shift = StaffShift::where('user_id', $request->user()->id)->where('status', 'open')->latest('id')->firstOrFail();
        try {
            $order = $svc->placeWalkIn($request->user(), $payload, (float) $data['cash'], $shift);
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
        Activity::log('pos', 'orders', "Walk-in {$order->order_number} (₱" . number_format($order->total, 2) . ')');

        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $order->id,
                'order_number'  => $order->order_number,
                'total'         => (float) $order->total,
                'change'        => round((float) $data['cash'] - (float) $order->total, 2),
                'receipt_url'   => route('staff.pos.receipt', $order),
            ],
        ], 201);
    }

    public function receipt(Order $order)
    {
        $order->load(['items.options', 'staff:id,name', 'user:id,name', 'payments']);

        $cash = null;
        $change = null;
        $ref = optional($order->payments->first())->reference_no;
        if ($ref && preg_match('/cash=([\d.]+);change=([\d.]+)/', $ref, $m)) {
            $cash = (float) $m[1];
            $change = (float) $m[2];
        }

        return view('staff.pos.receipt', compact('order', 'cash', 'change'));
    }
}
