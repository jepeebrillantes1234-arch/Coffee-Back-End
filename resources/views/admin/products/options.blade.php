@extends('layouts.admin')
@section('title', 'Options: ' . $product->name)
@section('content')
<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Customization: {{ $product->name }}</h1>
    <a href="{{ route('admin.products.index') }}" class="text-sm text-caramel font-medium">← Bumalik sa Products</a>
</div>
<p class="text-sm text-espresso/60 mb-6">Base price: ₱{{ number_format($product->base_price, 2) }}. Idadagdag ang "extra price" ng napiling option sa presyo.</p>

@if ($product->optionGroups->isEmpty())
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <p class="font-medium mb-3">Mabilis: ilagay ang standard coffee options</p>
    <p class="text-sm text-espresso/60 mb-4">Size, Sugar Level, Milk, Add-ons (at Ice Level kung iced).</p>
    <div class="flex gap-3">
        <form method="POST" action="{{ route('admin.products.options.preset', $product) }}">
            @csrf <input type="hidden" name="iced" value="0">
            <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">☕ Hot Coffee preset</button>
        </form>
        <form method="POST" action="{{ route('admin.products.options.preset', $product) }}">
            @csrf <input type="hidden" name="iced" value="1">
            <button class="bg-caramel text-white px-4 py-2 rounded-lg text-sm">🧊 Iced Coffee preset</button>
        </form>
    </div>
</div>
@endif

<div class="space-y-4 mb-8">
@foreach ($product->optionGroups as $g)
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <div>
                <span class="font-semibold">{{ $g->name }}</span>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full {{ $g->is_required ? 'bg-caramel/15 text-caramel' : 'bg-gray-100 text-gray-500' }}">
                    {{ $g->is_required ? 'Required' : 'Optional' }}
                </span>
                <span class="ml-1 text-xs text-espresso/50">{{ $g->is_multiple ? "Hanggang {$g->max_select} ang pwedeng piliin" : 'Isa lang' }}</span>
            </div>
            <form method="POST" action="{{ route('admin.groups.destroy', $g) }}" onsubmit="return confirm('Burahin ang group at lahat ng option nito?')">
                @csrf @method('DELETE') <button class="text-red-600 text-sm">Burahin ang group</button>
            </form>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            @forelse ($g->options as $o)
                <form method="POST" action="{{ route('admin.options.destroy', $o) }}" class="inline">
                    @csrf @method('DELETE')
                    <span class="inline-flex items-center gap-2 bg-cream rounded-full pl-3 pr-2 py-1 text-sm">
                        {{ $o->name }} @if ($o->extra_price > 0) <span class="text-espresso/60">+₱{{ number_format($o->extra_price, 0) }}</span> @endif
                        <button class="text-red-500 hover:text-red-700" title="Burahin">✕</button>
                    </span>
                </form>
            @empty
                <span class="text-sm text-espresso/50">Wala pang option.</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.groups.options.store', $g) }}" class="flex gap-2 items-end">
            @csrf
            <input name="name" placeholder="Pangalan (hal. Large)" required class="rounded-lg border border-espresso/20 px-3 py-1.5 text-sm w-48">
            <input type="number" step="0.01" min="0" name="extra_price" placeholder="Dagdag ₱" class="rounded-lg border border-espresso/20 px-3 py-1.5 text-sm w-28">
            <button class="bg-espresso text-cream px-3 py-1.5 rounded-lg text-sm">+ Option</button>
        </form>
    </div>
@endforeach
</div>

<div class="bg-white rounded-2xl shadow-sm p-6 max-w-2xl">
    <p class="font-medium mb-4">Magdagdag ng bagong group</p>
    <form method="POST" action="{{ route('admin.products.groups.store', $product) }}" class="space-y-4">
        @csrf
        <div>
            <input name="name" placeholder="Pangalan ng group (hal. Syrup)" required
                   class="w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm">
            @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-wrap gap-5 text-sm items-center">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1"> Required</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_multiple" value="1"> Puwedeng higit sa isa</label>
            <label class="flex items-center gap-2">Max: <input type="number" name="max_select" min="1" max="10" value="3" class="w-16 rounded border border-espresso/20 px-2 py-1"></label>
        </div>
        <button class="bg-caramel text-white px-4 py-2 rounded-lg text-sm">+ Group</button>
    </form>
</div>
@endsection
