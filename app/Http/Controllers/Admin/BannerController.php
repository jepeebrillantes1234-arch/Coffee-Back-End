<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\Activity;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->orderByDesc('id')->get();
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.form', ['banner' => new Banner(['is_active' => true, 'audience' => 'all', 'sort_order' => 0]), 'tokens' => $this->tokens()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) $data['image'] = $request->file('image')->store('banners', 'public');

        $b = Banner::create($data);
        Activity::log('create', 'banners', "Nagdagdag ng banner: {$b->headline}");
        return redirect()->route('admin.banners.index')->with('success', 'Naidagdag ang banner.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', ['banner' => $banner, 'tokens' => $this->tokens()]);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            if ($banner->image) Storage::disk('public')->delete($banner->image);
            $data['image'] = $request->file('image')->store('banners', 'public');
        } elseif ($request->boolean('remove_image') && $banner->image) {
            Storage::disk('public')->delete($banner->image);
            $data['image'] = null;
        }

        $banner->update($data);
        Activity::log('update', 'banners', "Ni-update ang banner: {$banner->headline}");
        return redirect()->route('admin.banners.index')->with('success', 'Na-update ang banner.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) Storage::disk('public')->delete($banner->image);
        Activity::log('delete', 'banners', "Binura ang banner: {$banner->headline}");
        $banner->delete();
        return back()->with('success', 'Nabura ang banner.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'      => 'nullable|string|max:60',
            'headline'   => 'required|string|max:40',
            'subtitle'   => 'nullable|string|max:80',
            'image'      => 'nullable|image|max:2048',
            'audience'   => 'required|in:all,new_customers',
            'sort_order' => 'nullable|integer|min:0',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);
        unset($data['image']);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active');
        $data['starts_at']  = !empty($data['starts_at']) ? Carbon::parse($data['starts_at'])->startOfDay() : null;
        $data['ends_at']    = !empty($data['ends_at']) ? Carbon::parse($data['ends_at'])->endOfDay() : null;
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
