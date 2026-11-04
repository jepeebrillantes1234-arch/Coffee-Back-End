<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Product;
use App\Models\PromoCode;
use App\Support\Activity;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $section = $request->query('section') === 'popups' ? 'popups' : 'home';
        $query = Banner::query();
        $section === 'popups'
            ? $query->where('show_as_popup', true)
            : $query->where('show_as_popup', false);

        $banners = $query->orderBy('sort_order')->orderByDesc('id')->get();

        return view('admin.banners.index', compact('banners', 'section'));
    }

    public function create(Request $request)
    {
        $section = $request->query('section') === 'popups' ? 'popups' : 'home';
        $isPopup = $section === 'popups';
        $banner = new Banner([
            'is_active' => true,
            'audience' => 'all',
            'banner_type' => $isPopup ? 'promo' : 'order_now',
            'destination' => $isPopup ? 'promos' : 'menu',
            'sort_order' => 0,
            'show_as_popup' => $isPopup,
        ]);

        return view('admin.banners.form', [
            'banner' => $banner,
            'section' => $section,
            'tokens' => $this->tokens(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'promoCodes' => PromoCode::orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $isPopup = $request->input('section') === 'popups';
        $data = $this->validated($request, $isPopup);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('banners', 'public');

        $b = Banner::create($data);
        Activity::log('create', 'banners', "Banner created: {$b->headline}");
        return redirect()->route('admin.banners.index', $isPopup ? ['section' => 'popups'] : [])->with('success', 'Banner added successfully.');
    }

    public function edit(Banner $banner)
    {
        $section = $banner->show_as_popup ? 'popups' : 'home';

        return view('admin.banners.form', [
            'banner' => $banner,
            'section' => $section,
            'tokens' => $this->tokens(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'promoCodes' => PromoCode::orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function update(Request $request, Banner $banner)
    {
        $requestedSection = $request->input('section');
        $isPopup = in_array($requestedSection, ['home', 'popups'], true)
            ? $requestedSection === 'popups'
            : (bool) $banner->show_as_popup;
        $data = $this->validated($request, $isPopup);
        if ($request->hasFile('image')) {
            if ($banner->image) Storage::disk('public')->delete($banner->image);
            $data['image'] = $request->file('image')->store('banners', 'public');
        } elseif ($request->boolean('remove_image') && $banner->image) {
            Storage::disk('public')->delete($banner->image);
            $data['image'] = null;
        }

        $banner->update($data);
        Activity::log('update', 'banners', "Banner updated: {$banner->headline}");
        $section = $isPopup ? 'popups' : 'home';

        return redirect()->route('admin.banners.index', $section === 'popups' ? ['section' => 'popups'] : [])->with('success', 'Banner updated successfully.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) Storage::disk('public')->delete($banner->image);
        Activity::log('delete', 'banners', "Banner deleted: {$banner->headline}");
        $banner->delete();
        return back()->with('success', 'Banner deleted successfully.');
    }

    private function validated(Request $request, bool $showAsPopup = false): array
    {
        $rules = [
            'title'      => 'nullable|string|max:60',
            'headline'   => 'required|string|max:40',
            'description' => 'nullable|string|max:1000',
            'button_text' => 'nullable|string|max:30',
            'image'      => 'nullable|image|max:2048',
            'banner_type' => 'required|in:order_now,promo,membership,redeem,rewards,custom,new_product,new_promo,promo_code,special_offer,announcement',
            'destination' => 'required|in:menu,promos,profile,membership,cart,orders,product,promo_code,offer,announcement',
            'audience'   => 'required|in:all,new_customers',
            'sort_order' => 'nullable|integer|min:0',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ];
        if ($request->input('destination') === 'product') {
            $rules['destination_id'] = 'required|integer|exists:products,id';
        } elseif ($request->input('destination') === 'promo_code') {
            $rules['destination_id'] = 'required|integer|exists:promo_codes,id';
        } else {
            $rules['destination_id'] = 'nullable|integer';
        }
        $data = $request->validate($rules);
        unset($data['image']);

        $data['show_as_popup'] = $showAsPopup;
        if ($showAsPopup && ! in_array($data['banner_type'], ['membership', 'promo'], true)) {
            throw ValidationException::withMessages([
                'show_as_popup' => 'Home popups must use the Membership or Promo banner type.',
            ]);
        }
        if ($showAsPopup && blank($data['button_text'] ?? null)) {
            throw ValidationException::withMessages([
                'button_text' => 'Enter button text for a Home popup.',
            ]);
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active');
        $data['starts_at']  = !empty($data['starts_at']) ? Carbon::parse($data['starts_at'], 'Asia/Manila')->utc() : null;
        $data['ends_at']    = !empty($data['ends_at']) ? Carbon::parse($data['ends_at'], 'Asia/Manila')->utc() : null;
        return $data;
    }

    /** Para sa preview: ang tunay na laman ng {percent} at {max} */
    private function tokens(): array
    {
        $fmt = fn ($v) => (float) $v == floor((float) $v) ? (string) (int) $v : (string) (float) $v;
        return [
            'percent' => $fmt(Settings::get('first_order_percent', 0)),
            'max'     => $fmt(Settings::get('first_order_max', 0)),
        ];
    }
}
