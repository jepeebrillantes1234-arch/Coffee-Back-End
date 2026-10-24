@extends('layouts.admin')
@section('title', 'Products')
@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold">Products</h1>
    <a href="{{ route('admin.products.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90 text-center">+ New Product</a>
</div>

<form method="GET" class="flex flex-col sm:flex-row gap-3 mb-6">
    <input name="search" value="{{ request('search') }}" placeholder="Search product..."
            class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-full sm:w-64">
    <select name="category_id" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-full sm:w-auto">
        <option value="">All categories</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm w-full sm:w-auto">Search</button>
</form>

<!-- ================= MOBILE VIEW (CARDS) - Makikita lang sa cellphone ================= -->
<div class="block xl:hidden space-y-3">
    @forelse ($products as $p)
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-espresso/10 flex gap-4 items-start">
            <!-- Product Image -->
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-cream">
                @if ($p->image_url)
                    <img src="{{ $p->image_url }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-xs text-espresso/40">No Image</div>
                @endif
            </div>

            <!-- Product Details -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-espresso truncate text-base">{{ $p->name }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] whitespace-nowrap {{ $p->is_available ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-500' }}">
                        {{ $p->is_available ? 'Available' : 'Sold out' }}
                    </span>
                </div>

                <p class="text-xs text-espresso/60 mb-1">{{ $p->category->name }}</p>

                <!-- Price -->
                <div class="text-sm font-medium mb-3">
                    @if($p->discount_active)
                        <span class="line-through text-espresso/50 text-xs">₱{{ number_format($p->base_price,2) }}</span>
                        <span class="text-green-700 font-semibold">₱{{ number_format($p->sale_price,2) }}</span>
                    @else
                        <span class="text-espresso font-semibold">₱{{ number_format($p->base_price, 2) }}</span>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs pt-2 border-t border-espresso/10">
                    <a href="{{ route('admin.products.options', $p) }}" class="text-espresso/70 font-medium">Options</a>
                    <a href="{{ route('admin.products.edit', $p) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $p) }}" class="inline ml-auto"
                          onsubmit="return confirm('Delete this product?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 font-medium">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white p-6 rounded-2xl text-center text-espresso/50">No products found.</div>
    @endforelse
</div>

<!-- ================= DESKTOP VIEW (TABLE) - Makikita lang sa laptop/PC ================= -->
<div class="hidden xl:block bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto w-full">
        <table class="w-full text-sm">
            <thead class="bg-espresso/5 text-left text-espresso/70">
                <tr>
                    <th class="p-3">Image</th>
                    <th class="p-3">Name</th>
                    <th class="p-3">Category</th>
                    <th class="p-3">Price</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $p)
                <tr class="border-t border-espresso/10">
                    <td class="p-3">
                        @if ($p->image_url) <img src="{{ $p->image_url }}" class="w-12 h-12 rounded-lg object-cover">
                        @else <div class="w-12 h-12 rounded-lg bg-cream"></div> @endif
                    </td>
                    <td class="p-3 font-medium">{{ $p->name }}</td>
                    <td class="p-3">{{ $p->category->name }}</td>
                    <td class="p-3">
                        @if($p->discount_active)
                            <span class="line-through text-espresso/50">₱{{ number_format($p->base_price,2) }}</span>
                            <span class="block text-green-700">₱{{ number_format($p->sale_price,2) }}</span>
                        @else
                            ₱{{ number_format($p->base_price, 2) }}
                        @endif
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs {{ $p->is_available ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-500' }}">
                            {{ $p->is_available ? 'Available' : 'Sold out' }}
                        </span>
                    </td>
                    <td class="p-3 text-right space-x-3">
                        <a href="{{ route('admin.products.options', $p) }}" class="text-espresso/70">Options</a>
                        <a href="{{ route('admin.products.edit', $p) }}" class="text-caramel font-medium">Edit</a>
                        <form method="POST" action="{{ route('admin.products.destroy', $p) }}" class="inline"
                              onsubmit="return confirm('Delete this product?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-espresso/50">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
