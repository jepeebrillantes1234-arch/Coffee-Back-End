@extends('layouts.admin')
@section('title', $banner->exists ? 'Edit Banner' : 'Bagong Banner')
@section('content')
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel'; @endphp
<h1 class="text-2xl font-semibold mb-6">{{ $banner->exists ? 'Edit Banner' : 'Bagong Banner' }}</h1>

<div class="grid xl:grid-cols-2 gap-6">
<form method="POST" enctype="multipart/form-data"
      action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
      class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
    @csrf
    @if ($banner->exists) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1">Maliit na text (sa taas)</label>
        <input id="f-title" name="title" maxlength="60" value="{{ old('title', $banner->title) }}" placeholder="First Order Discount" class="{{ $in }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Malaking text <span class="text-red-500">*</span></label>
        <input id="f-headline" name="headline" maxlength="40" required value="{{ old('headline', $banner->headline) }}" placeholder="GET {percent}% OFF" class="{{ $in }}">
        @error('headline') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Text sa ibaba</label>
        <input id="f-subtitle" name="subtitle" maxlength="80" value="{{ old('subtitle', $banner->subtitle) }}" placeholder="on your order!" class="{{ $in }}">
    </div>

    <div class="text-xs bg-cream rounded-lg px-3 py-2 text-espresso/80">
        💡 Isulat ang <b>{percent}</b> para kusang kunin ang First Order Discount % mula sa <i>Discount &amp; Fees</i>
        (ngayon: <b>{{ $tokens['percent'] }}%</b>), at <b>{max}</b> para sa limit (ngayon: <b>₱{{ $tokens['max'] }}</b>).
        Kapag binago mo ang discount doon, susunod agad ang banner.
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Larawan</label>
        @if ($banner->image)
            <div class="flex items-center gap-3 mb-2">
                <img src="{{ asset('storage/' . $banner->image) }}" class="w-24 h-16 rounded-lg object-cover">
                <label class="text-sm flex items-center gap-2"><input type="checkbox" name="remove_image" value="1"> Alisin ang larawan</label>
            </div>
        @endif
        <input id="f-image" type="file" name="image" accept="image/*" class="text-sm">
        <p class="text-xs text-espresso/50 mt-1">Mas maganda ang patayong larawan na mga 600×700 px, nasa gitna ang kape. Hanggang 2 MB.</p>
        @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Ipakita kanino</label>
            <select name="audience" class="{{ $in }}">
                <option value="all" @selected(old('audience', $banner->audience) === 'all')>Lahat ng customer</option>
                <option value="new_customers" @selected(old('audience', $banner->audience) === 'new_customers')>Bagong customer lang (wala pang order)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Pagkakasunod-sunod</label>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" class="{{ $in }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Simula <span class="text-espresso/40">opsyonal</span></label>
            <input type="date" name="starts_at" value="{{ old('starts_at', $banner->starts_at?->format('Y-m-d')) }}" class="{{ $in }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Hanggang <span class="text-espresso/40">opsyonal</span></label>
            <input type="date" name="ends_at" value="{{ old('ends_at', $banner->ends_at?->format('Y-m-d')) }}" class="{{ $in }}">
            @error('ends_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active))> Aktibo (ipakita sa app)</label>

    <div class="flex gap-3 pt-2">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">I-save</button>
        <a href="{{ route('admin.banners.index') }}" class="px-5 py-2 rounded-lg border border-espresso/20">Kanselahin</a>
    </div>
</form>

{{-- ========== PREVIEW ========== --}}
<div>
    <p class="text-sm font-medium mb-2">Preview (ganito ang itsura sa app)</p>
    <div class="rounded-3xl p-4" style="background:#3b2a24">
        <div class="flex rounded-[20px] overflow-hidden border-2 border-white shadow-lg h-44" style="background:linear-gradient(135deg,#9A6A3A,#5B3A26)">
            <div class="flex-1 flex flex-col justify-center px-4 py-3 text-white min-w-0">
                <p id="pv-title" class="italic text-[15px] font-serif"></p>
                <p id="pv-headline" class="font-black italic text-3xl tracking-wide leading-tight break-words"></p>
                <p id="pv-subtitle" class="italic text-[15px] font-serif"></p>
            </div>
            <div id="pv-img-wrap" class="w-36 shrink-0 {{ $banner->image ? '' : 'hidden' }}">
                <img id="pv-img" src="{{ $banner->image ? asset('storage/' . $banner->image) : '' }}" class="w-full h-full object-cover">
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
const TOKENS = @json($tokens);
const fill = s => String(s || '').replaceAll('{percent}', TOKENS.percent).replaceAll('{max}', TOKENS.max);
const $ = id => document.getElementById(id);

function paint() {
  $('pv-title').textContent = fill($('f-title').value);
  $('pv-headline').textContent = fill($('f-headline').value) || 'Malaking text';
  $('pv-subtitle').textContent = fill($('f-subtitle').value);
}
['f-title', 'f-headline', 'f-subtitle'].forEach(id => $(id).addEventListener('input', paint));
$('f-image').addEventListener('change', e => {
  const f = e.target.files[0];
  if (!f) return;
  const r = new FileReader();
  r.onload = ev => { $('pv-img').src = ev.target.result; $('pv-img-wrap').classList.remove('hidden'); };
  r.readAsDataURL(f);
});
paint();
</script>
@endpush
