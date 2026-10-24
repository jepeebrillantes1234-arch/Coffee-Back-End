@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Categories</h1>
    <a href="{{ route('admin.categories.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">+ New Category</a>
</div>

<div class="grid gap-3 xl:hidden">
    @forelse ($categories as $c)
        <article class="flex min-w-0 items-center gap-3 rounded-2xl border border-espresso/10 bg-white p-3 shadow-sm sm:gap-4 sm:p-4">
            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-cream sm:h-20 sm:w-20">
                @if ($c->image_url)
                    <img src="{{ $c->image_url }}" alt="{{ $c->name }}" loading="lazy" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-xs text-espresso/40">No image</div>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="truncate font-semibold text-espresso">{{ $c->name }}</h2>
                <p class="mt-1 text-xs text-espresso/60">{{ $c->products_count }} products Â· Order {{ $c->sort_order }}</p>
                <span class="mt-2 inline-flex rounded-full px-2 py-1 text-xs {{ $c->is_active ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-500' }}">
                    {{ $c->is_active ? 'Active' : 'Hidden' }}
                </span>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-2 text-sm">
                <a href="{{ route('admin.categories.edit', $c) }}" class="font-medium text-caramel">Edit</a>
                <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" onsubmit="return confirm('Delete this category?')">
                    @csrf @method('DELETE')
                    <button class="font-medium text-red-600">Delete</button>
                </form>
            </div>
        </article>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center text-espresso/50">No categories found.</div>
    @endforelse
</div>

<div class="hidden xl:block bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Image</th><th class="p-3">Name</th><th class="p-3">Products</th><th class="p-3">Order</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th></tr>
        </thead>
        <tbody>
        @forelse ($categories as $c)
            <tr class="border-t border-espresso/10">
                <td class="p-3">
                    @if ($c->image_url) <img src="{{ $c->image_url }}" class="w-10 h-10 rounded-lg object-cover">
                    @else <div class="w-10 h-10 rounded-lg bg-cream"></div> @endif
                </td>
                <td class="p-3 font-medium">{{ $c->name }}</td>
                <td class="p-3">{{ $c->products_count }}</td>
                <td class="p-3">{{ $c->sort_order }}</td>
                <td class="p-3">
                    <span class="px-2 py-1 rounded-full text-xs {{ $c->is_active ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-500' }}">
                        {{ $c->is_active ? 'Active' : 'Hidden' }}
                    </span>
                </td>
                <td class="p-3 text-right space-x-3">
                    <a href="{{ route('admin.categories.edit', $c) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="inline"
                          onsubmit="return confirm('Delete this category?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-espresso/50">No categories found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection