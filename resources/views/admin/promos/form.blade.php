@extends('layouts.admin')
@section('title', $promo->exists ? 'Edit Promo' : 'New Promo')
@section('content')
@php
    $input = 'w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-caramel';
    $startValue = $promo->starts_at ? $promo->starts_at->format('Y-m-d\\TH:i') : '';
    $expiresValue = $promo->expires_at ? $promo->expires_at->format('Y-m-d\\TH:i') : '';
@endphp

<div class="mb-5 flex items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">{{ $promo->exists ? 'Edit Promo' : 'Create Promo' }}</h1>
        <p class="mt-1 text-sm text-espresso/60">Set the offer details customers will see in the app.</p>
    </div>
    <a href="{{ route('admin.promos.index') }}" class="rounded-lg border border-espresso/20 px-4 py-2 text-sm">Back to Promos</a>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $promo->exists ? route('admin.promos.update', $promo) : route('admin.promos.store') }}"
      class="max-w-4xl space-y-6 rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($promo->exists) @method('PUT') @endif

    <div class="grid gap-5 md:grid-cols-2">
        <div>
            <label for="code" class="mb-1 block text-sm font-medium">Promo code</label>
            <input id="code" name="code" value="{{ old('code', $promo->code) }}" maxlength="30" required class="{{ $input }}" placeholder="e.g. COFFEE20">
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="type" class="mb-1 block text-sm font-medium">Offer type</label>
            <select id="type" name="type" required class="{{ $input }}">
                <option value="percent" @selected(old('type', $promo->type) === 'percent')>Percentage discount</option>
                <option value="fixed" @selected(old('type', $promo->type) === 'fixed')>Fixed amount discount</option>
                <option value="bogo" @selected(old('type', $promo->type) === 'bogo')>Buy X Get Y Free</option>
            </select>
            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div id="value-field">
            <label for="value" class="mb-1 block text-sm font-medium">Discount value</label>
            <input id="value" type="number" name="value" min="0.01" max="99999" step="0.01" value="{{ old('value', $promo->value) }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-espresso/60">Enter a percentage for Percentage or pesos for Fixed amount.</p>
            @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div id="bogo-fields" class="grid grid-cols-2 gap-3">
            <div>
                <label for="buy_qty" class="mb-1 block text-sm font-medium">Buy quantity</label>
                <input id="buy_qty" type="number" name="buy_qty" min="1" max="20" value="{{ old('buy_qty', $promo->buy_qty ?? 1) }}" class="{{ $input }}">
                @error('buy_qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="free_qty" class="mb-1 block text-sm font-medium">Free quantity</label>
                <input id="free_qty" type="number" name="free_qty" min="1" max="20" value="{{ old('free_qty', $promo->free_qty ?? 1) }}" class="{{ $input }}">
                @error('free_qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="min_order" class="mb-1 block text-sm font-medium">Minimum order (₱)</label>
            <input id="min_order" type="number" name="min_order" min="0" step="0.01" value="{{ old('min_order', $promo->min_order ?? 0) }}" class="{{ $input }}">
            @error('min_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div id="max-discount-field">
            <label for="max_discount" class="mb-1 block text-sm font-medium">Maximum discount (₱, optional)</label>
            <input id="max_discount" type="number" name="max_discount" min="0" step="0.01" value="{{ old('max_discount', $promo->max_discount) }}" class="{{ $input }}">
            @error('max_discount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="usage_limit" class="mb-1 block text-sm font-medium">Total uses (optional)</label>
            <input id="usage_limit" type="number" name="usage_limit" min="1" value="{{ old('usage_limit', $promo->usage_limit) }}" class="{{ $input }}">
            @error('usage_limit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="per_user_limit" class="mb-1 block text-sm font-medium">Uses per customer</label>
            <input id="per_user_limit" type="number" name="per_user_limit" min="1" value="{{ old('per_user_limit', $promo->per_user_limit ?? 1) }}" required class="{{ $input }}">
            @error('per_user_limit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="starts_at" class="mb-1 block text-sm font-medium">Starts at (optional)</label>
            <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $startValue) }}" class="{{ $input }}">
            @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="expires_at" class="mb-1 block text-sm font-medium">Expires at (optional)</label>
            <input id="expires_at" type="datetime-local" name="expires_at" value="{{ old('expires_at', $expiresValue) }}" class="{{ $input }}">
            @error('expires_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="product_id" class="mb-1 block text-sm font-medium">Product (required for Buy X Get Y)</label>
            <select id="product_id" name="product_id" class="{{ $input }}">
                <option value="">Whole order / select for Buy X Get Y</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((string) old('product_id', $promo->product_id) === (string) $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
            @error('product_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="sort_order" class="mb-1 block text-sm font-medium">Display order</label>
            <input id="sort_order" type="number" name="sort_order" min="0" value="{{ old('sort_order', $promo->sort_order ?? 0) }}" class="{{ $input }}">
            @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="title" class="mb-1 block text-sm font-medium">Small title (optional)</label>
            <input id="title" name="title" maxlength="60" value="{{ old('title', $promo->title) }}" class="{{ $input }}">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="headline" class="mb-1 block text-sm font-medium">Headline (optional)</label>
            <input id="headline" name="headline" maxlength="40" value="{{ old('headline', $promo->headline) }}" class="{{ $input }}">
            @error('headline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="md:col-span-2">
            <label for="description" class="mb-1 block text-sm font-medium">Description (optional)</label>
            <textarea id="description" name="description" maxlength="100" rows="2" class="{{ $input }}">{{ old('description', $promo->description) }}</textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label for="image" class="mb-1 block text-sm font-medium">Promo image (optional, max 2 MB)</label>
            <div class="flex flex-wrap items-center gap-4">
                @if ($promo->image)
                    <img src="{{ asset('storage/' . $promo->image) }}" alt="Current promo image" class="h-20 w-28 rounded-lg object-cover">
                @endif
                <input id="image" type="file" name="image" accept="image/*" class="text-sm">
            </div>
            @if ($promo->exists && $promo->image)
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
            @endif
            @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap gap-6 md:col-span-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promo->is_active))>
                Promo is active
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_in_app" value="1" @checked(old('show_in_app', $promo->show_in_app))>
                Show in customer app
            </label>
        </div>
    </div>

    <div class="flex gap-3 border-t border-espresso/10 pt-5">
        <button type="submit" class="rounded-lg bg-caramel px-5 py-2 font-medium text-white">{{ $promo->exists ? 'Save Changes' : 'Create Promo' }}</button>
        <a href="{{ route('admin.promos.index') }}" class="rounded-lg border border-espresso/20 px-5 py-2">Cancel</a>
    </div>
</form>

<script>
    (() => {
        const type = document.getElementById('type');
        const bogo = document.getElementById('bogo-fields');
        const value = document.getElementById('value-field');
        const maxDiscount = document.getElementById('max-discount-field');
        const product = document.getElementById('product_id');
        const update = () => {
            const isBogo = type.value === 'bogo';
            bogo.hidden = !isBogo;
            value.hidden = isBogo;
            maxDiscount.hidden = isBogo;
            product.required = isBogo;
        };
        type.addEventListener('change', update);
        update();
    })();
</script>
@endsection
