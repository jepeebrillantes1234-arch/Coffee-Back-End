@extends('layouts.admin')
@section('title', 'Products')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Products</h1>
    <a href="{{ route('admin.products.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">+ Bagong Product</a>
</div>

<form method="GET" class="flex gap-3 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Hanapin ang product..."
           class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-64">
    <select name="category_id" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">Lahat ng category</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Hanapin</button>
</form>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Larawan</th><th class="p-3">Pangalan</th><th class="p-3">Category</th><th class="p-3">Presyo</th><th class="p-3">Status</th><th class="p-3 text-right">Aksyon</th></tr>
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
                <td class="p-3">₱{{ number_format($p->base_price, 2) }}</td>
                <td class="p-3">
                    <span class="px-2 py-1 rounded-full text-xs {{ $p->is_available ? 'bg-success/10 text-success' : 'bg-gray-100 text-gray-500' }}">
                        {{ $p->is_available ? 'Available' : 'Sold out' }}
                    </span>
                </td>
                <td class="p-3 text-right space-x-3">
                    <a href="{{ route('admin.products.options', $p) }}" class="text-espresso/70">Options</a>
                    <a href="{{ route('admin.products.edit', $p) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $p) }}" class="inline"
                          onsubmit="return confirm('Burahin ang product na ito?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600">Burahin</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-espresso/50">Walang product na nahanap.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
