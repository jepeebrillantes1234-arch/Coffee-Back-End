@extends('layouts.admin')
@section('title', 'Promos')
@section('content')
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-caramel'; @endphp

<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Promos</h1>
    <a href="{{ route('admin.promos.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ New Promo</a>
</div>
<p class="text-sm text-espresso/60 mb-6">All promos with <b>"Show in app"</b> enabled will automatically appear on the Customer App Home screen as a sliding banner. You can add as many as you like.</p>

{{-- ========== FIRST ORDER DISCOUNT BANNER ========== --}}
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <p class="font-semibold">🎁 First Order Discount <span class="text-xs font-normal text-espresso/50">(automatic, no code required · for new customers only)</span></p>
    <p class="text-xs text-espresso/60 mt-1">The % comes from <a class="text-caramel" href="{{ route('admin.settings.edit') }}">Discount &amp; Fees</a> (current: <b>{{ $tokens['percent'] }}%</b>, limit <b>₱{{ $tokens['max'] }}</b>). Type <b>{percent}</b> or <b>{max}</b> to have the banner update automatically.</p>

    <form method="POST" action="{{ route('admin.promos.first-order') }}" enctype="multipart/form-data" class="grid md:grid-cols-4 gap-4 mt-4 items-end">
        @csrf @method('PUT')
        <div><label class="block text-xs mb-1">Small text</label><input name="fo_title" value="{{ old('fo_title', $fo['title']) }}" maxlength="60" class="{{ $in }}"></div>
        <div><label class="block text-xs mb-1">Large text</label><input name="fo_headline" value="{{ old('fo_headline', $fo['headline']) }}" maxlength="40" required class="{{ $in }}"></div>
        <div><label class="block text-xs mb-1">Bottom text</label><input name="fo_subtitle" value="{{ old('fo_subtitle', $fo['subtitle']) }}" maxlength="80" class="{{ $in }}"></div>
        <div>
            <label class="block text-xs mb-1">Image</label>
            <div class="flex items-center gap-2">
                @if ($fo['image']) <img src="{{ asset('storage/' . $fo['image']) }}" class="w-12 h-12 rounded-lg object-cover"> @endif
                <input type="file" name="fo_image" accept="image/*" class="text-xs w-full">
            </div>
            @if ($fo['image']) <label class="text-xs flex items-center gap-1 mt-1"><input type="checkbox" name="remove_fo_image" value="1"> Remove image</label> @endif
        </div>
        <div class="md:col-span-4"><button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Save First Order banner</button></div>
    </form>
    @error('fo_image') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>

{{-- ========== PROMO LIST ========== --}}
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Image</th><th class="p-3">Promo</th><th class="p-3">Discount</th><th class="p-3">Product</th><th class="p-3">Usage</th><th class="p-3">Validity</th><th class="p-3">In App</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th></tr>
        </thead>
        <tbody>
        @forelse ($promos as $p)
            <tr class="border-t border-espresso/10">
                <td class="p-3">
                    @if ($p->image) <img src="{{ asset('storage/' . $p->image) }}" class="w-16 h-12 rounded-lg object-cover">
                    @else <div class="w-16 h-12 rounded-lg bg-cream flex items-center justify-center text-[10px] text-espresso/40 text-center leading-tight">no<br>image</div> @endif
                </td>
                <td class="p-3">
                    <p class="font-semibold tracking-wide">{{ $p->code }}</p>
                    <p class="text-xs text-espresso/60">{{ $p->headline ?: $p->title }}</p>
                </td>
                <td class="p-3">
                    @if ($p->type === 'bogo') Buy {{ $p->buy_qty }} Get {{ $p->free_qty }} Free
                    @elseif ($p->type === 'percent') {{ rtrim(rtrim(number_format($p->value, 2), '0'), '.') }}%
                    @else ₱{{ number_format($p->value, 2) }} @endif
                    @if ($p->max_discount) <span class="text-xs text-espresso/50">(max ₱{{ number_format($p->max_discount, 0) }})</span> @endif
                </td>
                <td class="p-3">{{ $p->product->name ?? 'Whole order' }}</td>
                <td class="p-3">{{ $p->usages_count }}{{ $p->usage_limit ? ' / ' . $p->usage_limit : '' }}<span class="block text-[11px] text-espresso/50">{{ $p->per_user_limit }}× per customer</span></td>
                <td class="p-3 text-xs">
                    {{ $p->starts_at ? $p->starts_at->format('M d') : '—' }} → {{ $p->expires_at ? $p->expires_at->format('M d, Y') : 'indefinite' }}
                    @if ($p->isExpired()) <span class="text-red-600">(expired)</span> @endif
                </td>
                <td class="p-3">{{ $p->show_in_app ? '📱 Yes' : '—' }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $p->is_active ? 'active' : 'inactive'])</td>
                <td class="p-3 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('admin.promos.edit', $p) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.promos.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete promo?')">
                        @csrf @method('DELETE') <button class="text-red-600">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="p-6 text-center text-espresso/50">No promos found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection