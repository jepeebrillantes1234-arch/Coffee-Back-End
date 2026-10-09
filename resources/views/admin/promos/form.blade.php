@extends('layouts.admin')
@section('title', $promo->exists ? 'Edit Promo' : 'Bagong Promo')
@section('content')
@php
    $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel';
    $type = old('type', $promo->type);
@endphp
<h1 class="text-2xl font-semibold mb-6">{{ $promo->exists ? 'Edit Promo' : 'Bagong Promo' }}</h1>

<div class="grid xl:grid-cols-2 gap-6">
<form method="POST" enctype="multipart/form-data"
      action="{{ $promo->exists ? route('admin.promos.update', $promo) : route('admin.promos.store') }}"
      class="bg-white rounded-2xl shadow-sm p-6 grid sm:grid-cols-2 gap-4">
    @csrf
    @if ($promo->exists) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1">Code</label>
        <input id="f-code" name="code" value="{{ old('code', $promo->code) }}" required class="{{ $in }} uppercase" placeholder="BUY1GET1">
        @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Uri ng promo</label>
        <select id="f-type" name="type" class="{{ $in }}">
            <option value="percent" @selected($type === 'percent')>Percent (%)</option>
            <option value="fixed" @selected($type === 'fixed')>Fixed (₱)</option>
            <option value="bogo" @selected($type === 'bogo')>Buy X Get Y (libre)</option>
        </select>
    </div>

    <div id="row-value">
        <label class="block text-sm font-medium mb-1">Halaga</label>
        <input id="f-value" type="number" step="0.01" name="value" value="{{ old('value', $promo->value > 0 ? $promo->value : '') }}" class="{{ $in }}">
        @error('value') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div id="row-bogo" class="sm:col-span-2 grid grid-cols-2 gap-4 hidden">
        <div>
            <label class="block text-sm font-medium mb-1">Bumili ng (piraso)</label>
            <input id="f-buy" type="number" min="1" max="20" name="buy_qty" value="{{ old('buy_qty', $promo->buy_qty ?? 1) }}" class="{{ $in }}">
            @error('buy_qty') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Libre ang (piraso)</label>
            <input id="f-free" type="number" min="1" max="20" name="free_qty" value="{{ old('free_qty', $promo->free_qty ?? 1) }}" class="{{ $in }}">
            @error('free_qty') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <p class="col-span-2 text-xs text-espresso/60">
            Halimbawa: <b>1 at 1</b> = Buy 1 Get 1 (2 piraso, bayad ang 1). <b>1 at 2</b> = bumili ng 1, libre ang 2 (3 piraso, bayad ang 1).
            Isang beses lang gagana sa isang order. Kailangang pumili ng produkto sa ibaba.
        </p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1">Para sa produkto <span class="text-espresso/40 font-normal">(opsyonal; kailangan sa Buy X Get Y)</span></label>
        <select id="f-product" name="product_id" class="{{ $in }}">
            <option value="">— Buong order —</option>
            @foreach ($products as $pr)
                <option value="{{ $pr->id }}" @selected(old('product_id', $promo->product_id) == $pr->id)>{{ $pr->name }}</option>
            @endforeach
        </select>
        <p class="text-xs text-espresso/60 mt-1">Kapag may napiling produkto, ang diskwento ay sa <b>1 piraso</b> lang ng produktong iyon. Kapag pinindot ng customer ang promo sa app, ang produktong iyon mismo ang bubukas.</p>
        @error('product_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Minimum order (₱)</label>
        <input type="number" step="0.01" name="min_order" value="{{ old('min_order', $promo->min_order) }}" class="{{ $in }}">
    </div>
    <div id="row-max">
        <label class="block text-sm font-medium mb-1">Max diskwento (₱) <span class="text-espresso/40 font-normal">opsyonal</span></label>
        <input type="number" step="0.01" name="max_discount" value="{{ old('max_discount', $promo->max_discount) }}" class="{{ $in }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Kabuuang limit <span class="text-espresso/40 font-normal">opsyonal</span></label>
        <input type="number" name="usage_limit" value="{{ old('usage_limit', $promo->usage_limit) }}" class="{{ $in }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Ilang beses magagamit ng 1 customer</label>
        <input type="number" min="1" name="per_user_limit" value="{{ old('per_user_limit', $promo->per_user_limit ?? 1) }}" required class="{{ $in }}">
        <p class="text-xs text-espresso/50 mt-1">Ilagay ang <b>1</b> para isang beses lang.</p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Simula</label>
        <input type="date" name="starts_at" value="{{ old('starts_at', $promo->starts_at?->format('Y-m-d')) }}" class="{{ $in }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Hanggang</label>
        <input type="date" name="expires_at" value="{{ old('expires_at', $promo->expires_at?->format('Y-m-d')) }}" class="{{ $in }}">
        @error('expires_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 border-t border-espresso/10 pt-4">
        <p class="font-medium mb-3">📱 Itsura sa app</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Maliit na text</label>
                <input id="f-title" name="title" maxlength="60" value="{{ old('title', $promo->title) }}" class="{{ $in }}" placeholder="Espresso Promo">
            </div>
            <div>
                <label class="block text-sm mb-1">Malaking text <span class="text-espresso/40">(blangko = awtomatiko)</span></label>
                <input id="f-headline" name="headline" maxlength="40" value="{{ old('headline', $promo->headline) }}" class="{{ $in }}" placeholder="BUY 1 GET 2">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm mb-1">Text sa ibaba</label>
                <input id="f-desc" name="description" maxlength="100" value="{{ old('description', $promo->description) }}" class="{{ $in }}" placeholder="Libre ang pangalawa!">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm mb-1">Larawan</label>
                @if ($promo->image)
                    <div class="flex items-center gap-3 mb-2">
                        <img src="{{ asset('storage/' . $promo->image) }}" class="w-24 h-16 rounded-lg object-cover">
                        <label class="text-sm flex items-center gap-2"><input type="checkbox" name="remove_image" value="1"> Alisin ang larawan</label>
                    </div>
                @endif
                <input id="f-image" type="file" name="image" accept="image/*" class="text-sm">
                <p class="text-xs text-espresso/50 mt-1">Mas maganda ang patayong larawan na mga 600×700 px. Hanggang 2 MB. Papalitan mo lang ito para magbago ang nasa app.</p>
                @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_in_app" value="1" @checked(old('show_in_app', $promo->show_in_app))> Ipakita sa Home ng app</label>
            <div>
                <label class="block text-sm mb-1">Pagkakasunod-sunod</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $promo->sort_order) }}" class="{{ $in }}">
            </div>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promo->is_active))> Aktibo</label>
    <div class="sm:col-span-2 flex gap-3">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">I-save</button>
        <a href="{{ route('admin.promos.index') }}" class="px-5 py-2 rounded-lg border border-espresso/20">Kanselahin</a>
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
                <p id="pv-desc" class="italic text-[14px] font-serif"></p>
                <span id="pv-code" class="mt-2 inline-block self-start text-[11px] font-semibold px-2 py-1 rounded-full bg-white/20 border border-white/40"></span>
            </div>
            <div id="pv-img-wrap" class="w-36 shrink-0 {{ $promo->image ? '' : 'hidden' }}">
                <img id="pv-img" src="{{ $promo->image ? asset('storage/' . $promo->image) : '' }}" class="w-full h-full object-cover">
            </div>
        </div>
        <div class="flex gap-1 mt-3"><div class="h-1 flex-1 rounded bg-white"></div><div class="h-1 flex-1 rounded bg-white/30"></div><div class="h-1 flex-1 rounded bg-white/30"></div></div>
    </div>
    <p class="text-xs text-espresso/50 mt-2">Ang puting guhit sa ibaba ay ang "loading bar" na gumagalaw bawat 5 segundo sa app.</p>
</div>
</div>
@endsection

@push('scripts')
<script>
const $ = id => document.getElementById(id);

function autoHeadline() {
  const t = $('f-type').value, v = $('f-value').value;
  if (t === 'bogo') return 'BUY ' + ($('f-buy').value || 1) + ' GET ' + ($('f-free').value || 1) + ' FREE';
  if (t === 'percent') return 'GET ' + (v || 0) + '% OFF';
  return '₱' + (v || 0) + ' OFF';
}
function paint() {
  $('pv-title').textContent = $('f-title').value;
  $('pv-headline').textContent = $('f-headline').value || autoHeadline();
  $('pv-desc').textContent = $('f-desc').value;
  $('pv-code').textContent = 'CODE: ' + ($('f-code').value || '').toUpperCase() + ' · 1 gamit lang';
}
function syncType() {
  const bogo = $('f-type').value === 'bogo';
  $('row-value').classList.toggle('hidden', bogo);
  $('row-max').classList.toggle('hidden', bogo);
  $('row-bogo').classList.toggle('hidden', !bogo);
  $('f-product').required = bogo;
  paint();
}
['f-code','f-type','f-value','f-buy','f-free','f-title','f-headline','f-desc'].forEach(id => $(id).addEventListener('input', () => id === 'f-type' ? syncType() : paint()));
$('f-image').addEventListener('change', e => {
  const f = e.target.files[0]; if (!f) return;
  const r = new FileReader();
  r.onload = ev => { $('pv-img').src = ev.target.result; $('pv-img-wrap').classList.remove('hidden'); };
  r.readAsDataURL(f);
});
syncType();
</script>
@endpush
