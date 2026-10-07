<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromoCode;
use App\Support\Activity;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromoController extends Controller
{
    public function index()
    {
        $promos = PromoCode::with('product:id,name')->withCount('usages')->orderBy('sort_order')->latest('id')->get();

        $fo = [
            'title'    => Settings::get('fo_title', 'First Order Discount'),
            'headline' => Settings::get('fo_headline', 'GET {percent}% OFF'),
            'subtitle' => Settings::get('fo_subtitle', 'on your order!'),
            'image'    => Settings::get('fo_image') ?: null,
        ];

        return view('admin.promos.index', ['promos' => $promos, 'fo' => $fo, 'tokens' => $this->tokens()]);
    }

    public function create()
    {
        return view('admin.promos.form', [
            'promo' => new PromoCode(['is_active' => true, 'type' => 'percent', 'per_user_limit' => 1, 'show_in_app' => true, 'sort_order' => 0]),
            'products' => $this->products(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('promos', 'public');

        $promo = PromoCode::create($data);
        Activity::log('create', 'promos', "Nagdagdag ng promo: {$promo->code}");
        return redirect()->route('admin.promos.index')->with('success', 'Naidagdag ang promo.');
    }

    public function edit(PromoCode $promo)
    {
        return view('admin.promos.form', ['promo' => $promo, 'products' => $this->products()]);
    }

    public function update(Request $request, PromoCode $promo)
    {
        $data = $this->validated($request, $promo->id);

        if ($request->hasFile('image')) {
            if ($promo->image) Storage::disk('public')->delete($promo->image);
            $data['image'] = $request->file('image')->store('promos', 'public');
        } elseif ($request->boolean('remove_image') && $promo->image) {
            Storage::disk('public')->delete($promo->image);
            $data['image'] = null;
        }

        $promo->update($data);
        Activity::log('update', 'promos', "Ni-update ang promo: {$promo->code}");
        return redirect()->route('admin.promos.index')->with('success', 'Na-update ang promo.');
    }

    public function destroy(PromoCode $promo)
    {
        if ($promo->usages()->exists()) {
            $promo->update(['is_active' => false]);
            return back()->with('error', 'May gumamit na ng promo na ito, kaya dini-deactivate na lang (hindi binura).');
        }
        if ($promo->image) Storage::disk('public')->delete($promo->image);
        Activity::log('delete', 'promos', "Binura ang promo: {$promo->code}");
        $promo->delete();
        return back()->with('success', 'Nabura ang promo.');
    }

    /** Larawan at text ng awtomatikong First Order Discount banner */
    public function firstOrderBanner(Request $request)
    {
        $data = $request->validate([
            'fo_title'    => 'nullable|string|max:60',
            'fo_headline' => 'required|string|max:40',
            'fo_subtitle' => 'nullable|string|max:80',
            'fo_image'    => 'nullable|image|max:2048',
        ]);

        Settings::set('fo_title', $data['fo_title'] ?? '');
        Settings::set('fo_headline', $data['fo_headline']);
        Settings::set('fo_subtitle', $data['fo_subtitle'] ?? '');

        $old = Settings::get('fo_image');
        if ($request->hasFile('fo_image')) {
            if ($old) Storage::disk('public')->delete($old);
            Settings::set('fo_image', $request->file('fo_image')->store('promos', 'public'));
        } elseif ($request->boolean('remove_fo_image') && $old) {
            Storage::disk('public')->delete($old);
            Settings::set('fo_image', '');
        }

        Activity::log('update', 'promos', 'Ni-update ang First Order Discount banner');
        return back()->with('success', 'Na-save ang First Order Discount banner.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code'           => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('promo_codes', 'code')->ignore($ignoreId)],
            'type'           => 'required|in:percent,fixed,bogo',
            'value'          => 'required_unless:type,bogo|nullable|numeric|min:0.01|max:99999',
            'buy_qty'        => 'required_if:type,bogo|nullable|integer|min:1|max:20',
            'free_qty'       => 'required_if:type,bogo|nullable|integer|min:1|max:20',
            'min_order'      => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after_or_equal:starts_at',
            'title'          => 'nullable|string|max:60',
            'headline'       => 'nullable|string|max:40',
            'description'    => 'nullable|string|max:100',
            'image'          => 'nullable|image|max:2048',
            'product_id'     => 'required_if:type,bogo|nullable|integer|exists:products,id',
            'sort_order'     => 'nullable|integer|min:0',
        ]);

        if ($data['type'] === 'percent' && ($data['value'] ?? 0) > 100) {
            throw ValidationException::withMessages(['value' => 'Hindi puwedeng lampas 100% ang percent.']);
        }
        unset($data['image']);

        if ($data['type'] === 'bogo') {
            $data['value'] = 0;
            $data['max_discount'] = null;
        } else {
            $data['buy_qty'] = 1;
            $data['free_qty'] = 1;
        }

        $data['code']        = strtoupper($data['code']);
        $data['min_order']   = $data['min_order'] ?? 0;
        $data['sort_order']  = $data['sort_order'] ?? 0;
        $data['is_active']   = $request->boolean('is_active');
        $data['show_in_app'] = $request->boolean('show_in_app');
        $data['starts_at']   = !empty($data['starts_at']) ? Carbon::parse($data['starts_at'])->startOfDay() : null;
        $data['expires_at']  = !empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        return $data;
    }

    private function products()
    {
        return Product::orderBy('name')->get(['id', 'name']);
    }

    private function tokens(): array
    {
        $fmt = fn ($v) => (float) $v == floor((float) $v) ? (string) (int) $v : (string) (float) $v;
        return ['percent' => $fmt(Settings::get('first_order_percent', 0)), 'max' => $fmt(Settings::get('first_order_max', 0))];
    }
}
