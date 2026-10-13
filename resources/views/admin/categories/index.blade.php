@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Categories</h1>
    <a href="{{ route('admin.categories.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">+ New Category</a>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
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