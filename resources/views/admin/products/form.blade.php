@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'New Product')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $product->exists ? 'Edit Product' : 'New Product' }}</h1>

<form method="POST" enctype="multipart/form-data"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      class="bg-white rounded-2xl shadow-sm p-6 max-w-xl space-y-5">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1">Category</label>
        <select name="category_id" required class="w-full rounded-lg border border-espresso/20 px-3 py-2">
            <option value="">-- Select --</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('category_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Name</label>
        <input name="name" value="{{ old('name', $product->name) }}" required
               class="w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel">
        @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border border-espresso/20 px-3 py-2">{{ old('description', $product->description) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Base Price (₱)</label>
        <input type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $product->base_price) }}" required
               class="w-40 rounded-lg border border-espresso/20 px-3 py-2">
        @error('base_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Image</label>
        @if ($product->image_url) <img src="{{ $product->image_url }}" class="w-24 h-24 rounded-lg object-cover mb-2"> @endif
        <input type="file" name="image" accept="image/*" class="text-sm">
        @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <fieldset class="rounded-xl border border-espresso/15 p-4 space-y-3">
        <legend class="px-1 font-semibold">Scheduled product discount</legend>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="discount_is_active" value="1" @checked(old('discount_is_active', $product->discount_is_active))> Enable discount</label>
        <div class="flex flex-wrap gap-3">
            <select name="discount_type" class="rounded-lg border border-espresso/20 px-3 py-2"><option value="percent" @selected(old('discount_type',$product->discount_type)==='percent')>Percent</option><option value="fixed" @selected(old('discount_type',$product->discount_type)==='fixed')>Fixed amount</option></select>
            <input type="number" name="discount_value" min="0" step="0.01" value="{{ old('discount_value',$product->discount_value) }}" placeholder="Discount value" class="w-40 rounded-lg border border-espresso/20 px-3 py-2">
        </div>
        <div class="flex flex-wrap gap-3"><label class="text-xs">Starts <input type="datetime-local" name="discount_starts_at" value="{{ old('discount_starts_at', $product->discount_starts_at?->format('Y-m-d\\TH:i')) }}" class="block rounded border p-2"></label><label class="text-xs">Ends <input type="datetime-local" name="discount_ends_at" value="{{ old('discount_ends_at', $product->discount_ends_at?->format('Y-m-d\\TH:i')) }}" class="block rounded border p-2"></label></div>
    </fieldset>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $product->is_available))> Available (uncheck if out of stock)
    </label>

    <div class="flex gap-3 pt-2">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">Save</button>
        <a href="{{ route('admin.products.index') }}" class="px-5 py-2 rounded-lg border border-espresso/20">Cancel</a>
    </div>
</form>
@endsection
