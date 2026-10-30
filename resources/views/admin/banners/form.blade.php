@extends('layouts.admin')
@section('title', $banner->exists ? 'Edit Banner' : 'New Banner')
@section('content')
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel'; @endphp
<h1 class="text-2xl font-semibold mb-6">{{ $banner->exists ? 'Edit Banner' : 'New Banner' }}</h1>

<div class="grid xl:grid-cols-2 gap-6">
<form method="POST" enctype="multipart/form-data"
      action="{{ $banner->exists ? route('admin.banners.update', $banner, false) : route('admin.banners.store', [], false) }}"
      class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
    @csrf
    @if ($banner->exists) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1">Banner title</label>
        <input id="f-title" name="title" maxlength="60" value="{{ old('title', $banner->title) }}" placeholder="First Order Discount" class="{{ $in }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Headline <span class="text-red-500">*</span></label>
        <input id="f-headline" name="headline" maxlength="40" required value="{{ old('headline', $banner->headline) }}" placeholder="GET {percent}% OFF" class="{{ $in }}">
        @error('headline') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea id="f-description" name="description" maxlength="1000" rows="3" placeholder="Describe this banner" class="{{ $in }}">{{ old('description', $banner->description) }}</textarea>
        @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="text-xs bg-cream rounded-lg px-3 py-2 text-espresso/80">
        💡 Type <b>{percent}</b> to automatically pull the First Order Discount % from <i>Discount &amp; Fees</i>
        (current: <b>{{ $tokens['percent'] }}%</b>), and <b>{max}</b> for the limit (current: <b>₱{{ $tokens['max'] }}</b>).
        When you update the discount there, the banner will update instantly.
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Image</label>
        @if ($banner->image)
            <div class="flex items-center gap-3 mb-2">
                <img src="{{ asset('storage/' . $banner->image) }}" class="w-24 h-16 rounded-lg object-cover">
                <label class="text-sm flex items-center gap-2"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
            </div>
        @endif
        <input id="f-image" type="file" name="image" accept="image/*" class="text-sm">
        <p class="text-xs text-espresso/50 mt-1">Vertical image of around 600×700 px works best, with the coffee centered. Up to 2 MB.</p>
        @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Banner type</label>
            <select name="banner_type" class="{{ $in }}">
                @foreach (['new_product' => 'New Product', 'new_promo' => 'New Promo', 'promo_code' => 'Promo Code', 'special_offer' => 'Special Offer', 'announcement' => 'Announcement', 'order_now' => 'Order Now', 'promo' => 'Promo', 'membership' => 'Membership', 'redeem' => 'Redeem', 'rewards' => 'Rewards', 'custom' => 'Custom'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('banner_type', $banner->banner_type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('banner_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tap destination</label>
            <select name="destination" class="{{ $in }}">
                @foreach (['product' => 'Product (choose below)', 'promos' => 'Promotions', 'promo_code' => 'Promo codes / Promotions', 'offer' => 'Special offers / Promotions', 'announcement' => 'Announcement details', 'menu' => 'Menu / Order Now', 'profile' => 'Profile', 'cart' => 'Cart', 'orders' => 'My Orders'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('destination', $banner->destination) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('destination') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Button text</label>
            <input name="button_text" maxlength="30" value="{{ old('button_text', $banner->button_text) }}" placeholder="Shop now" class="{{ $in }}">
            @error('button_text') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Product / promo destination</label>
            <select name="destination_id" class="{{ $in }}">
                <option value="">Choose a product or promo code destination</option>
                <optgroup label="Products (for Product destination)">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('destination', $banner->destination) === 'product' && (string) old('destination_id', $banner->destination_id) === (string) $product->id)>{{ $product->name }}</option>
                @endforeach
                </optgroup>
                <optgroup label="Promo codes (for Promo Code destination)">
                @foreach ($promoCodes as $promoCode)
                    <option value="{{ $promoCode->id }}" @selected(old('destination') === 'promo_code' && (string) old('destination_id', $banner->destination_id) === (string) $promoCode->id)>{{ $promoCode->code }}</option>
                @endforeach
                </optgroup>
            </select>
            @error('destination_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Target audience</label>
            <select name="audience" class="{{ $in }}">
                <option value="all" @selected(old('audience', $banner->audience) === 'all')>All customers</option>
                <option value="new_customers" @selected(old('audience', $banner->audience) === 'new_customers')>New customers only (no orders yet)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Sort order</label>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" class="{{ $in }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Starts at <span class="text-espresso/40">optional</span></label>
            <input type="date" name="starts_at" value="{{ old('starts_at', $banner->starts_at?->format('Y-m-d')) }}" class="{{ $in }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Ends at <span class="text-espresso/40">optional</span></label>
            <input type="date" name="ends_at" value="{{ old('ends_at', $banner->ends_at?->format('Y-m-d')) }}" class="{{ $in }}">
            @error('ends_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active))> Active (show in app)</label>

    <div class="flex gap-3 pt-2">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">Save</button>
        <a href="{{ route('admin.banners.index') }}" class="px-5 py-2 rounded-lg border border-espresso/20">Cancel</a>
    </div>
</form>

{{-- ========== PREVIEW ========== --}}
<div>
    <p class="text-sm font-medium mb-2">Preview (how it looks in the app)</p>
    <div class="relative h-44 overflow-hidden rounded-[18px] bg-neutral-200">
        <img id="pv-img" src="{{ $banner->image ? asset('storage/' . $banner->image) : '' }}" class="absolute inset-0 h-full w-full object-cover {{ $banner->image ? '' : 'hidden' }}">
        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/35 to-transparent"></div>
        <div class="relative z-10 flex h-full max-w-[80%] flex-col justify-center px-5 py-4 text-white">
            <p id="pv-title" class="italic text-[15px] font-serif"></p>
            <p id="pv-headline" class="font-black italic text-3xl tracking-wide leading-tight break-words"></p>
            <p id="pv-description" class="italic text-[15px] font-serif"></p>
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
  $('pv-headline').textContent = fill($('f-headline').value) || 'Large text';
  $('pv-description').textContent = $('f-description').value;
}
['f-title', 'f-headline', 'f-description'].forEach(id => $(id).addEventListener('input', paint));
$('f-image').addEventListener('change', e => {
  const f = e.target.files[0];
  if (!f) return;
  const r = new FileReader();
  r.onload = ev => { $('pv-img').src = ev.target.result; $('pv-img').classList.remove('hidden'); };
  r.readAsDataURL(f);
});
paint();
</script>
@endpush
