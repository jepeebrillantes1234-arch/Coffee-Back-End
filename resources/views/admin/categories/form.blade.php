@extends('layouts.admin')
@section('title', $category->exists ? 'Edit Category' : 'Bagong Category')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $category->exists ? 'Edit Category' : 'Bagong Category' }}</h1>

<form method="POST" enctype="multipart/form-data"
      action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      class="bg-white rounded-2xl shadow-sm p-6 max-w-xl space-y-5">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1">Pangalan</label>
        <input name="name" value="{{ old('name', $category->name) }}" required
               class="w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel">
        @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Pagkakasunod-sunod</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}"
               class="w-32 rounded-lg border border-espresso/20 px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Larawan</label>
        @if ($category->image_url) <img src="{{ $category->image_url }}" class="w-20 h-20 rounded-lg object-cover mb-2"> @endif
        <input type="file" name="image" accept="image/*" class="text-sm">
        @error('image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> Active (makikita sa app)
    </label>

    <div class="flex gap-3 pt-2">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">I-save</button>
        <a href="{{ route('admin.categories.index') }}" class="px-5 py-2 rounded-lg border border-espresso/20">Kanselahin</a>
    </div>
</form>
@endsection
