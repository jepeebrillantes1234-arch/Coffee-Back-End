@extends('layouts.admin')
@section('title', 'Promos')
@section('content')
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-caramel'; @endphp

<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Promos</h1>
    <a href="{{ route('admin.promos.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ Bagong Promo</a>
</div>
<p class="text-sm text-espresso/60 mb-6">Lahat ng promo na may <b>"Ipakita sa app"</b> ay awtomatikong lalabas sa Home ng Customer App bilang gumagalaw na banner. Kahit ilan ang ilagay mo.</p>

{{-- ========== FIRST ORDER DISCOUNT BANNER ========== --}}
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <p class="font-semibold">🎁 First Order Discount <span class="text-xs font-normal text-espresso/50">(awtomatiko, walang code · para sa bagong customer lang)</span></p>
    <p class="text-xs text-espresso/60 mt-1">Ang % ay mula sa <a class="text-caramel" href="{{ route('admin.settings.edit') }}">Discount &amp; Fees</a> (ngayon: <b>{{ $tokens['percent'] }}%</b>, limit <b>₱{{ $tokens['max'] }}</b>). Isulat ang <b>{percent}</b> o <b>{max}</b> para kusang sumunod ang banner.</p>

    <form method="POST" action="{{ route('admin.promos.first-order') }}" enctype="multipart/form-data" class="grid md:grid-cols-4 gap-4 mt-4 items-end">
        @csrf @method('PUT')
        <div><label class="block text-xs mb-1">Maliit na text</label><input name="fo_title" value="{{ old('fo_title', $fo['title']) }}" maxlength="60" class="{{ $in }}"></div>
        <div><label class="block text-xs mb-1">Malaking text</label><input name="fo_headline" value="{{ old('fo_headline', $fo['headline']) }}" maxlength="40" required class="{{ $in }}"></div>
        <div><label class="block text-xs mb-1">Text sa ibaba</label><input name="fo_subtitle" value="{{ old('fo_subtitle', $fo['subtitle']) }}" maxlength="80" class="{{ $in }}"></div>
        <div>
            <label class="block text-xs mb-1">Larawan</label>
            <div class="flex items-center gap-2">
                @if ($fo['image']) <img src="{{ asset('storage/' . $fo['image']) }}" class="w-12 h-12 rounded-lg object-cover"> @endif
                <input type="file" name="fo_image" accept="image/*" class="text-xs w-full">
            </div>
            @if ($fo['image']) <label class="text-xs flex items-center gap-1 mt-1"><input type="checkbox" name="remove_fo_image" value="1"> Alisin ang larawan</label> @endif
        </div>
        <div class="md:col-span-4"><button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">I-save ang First Order banner</button></div>
    </form>
    @error('fo_image') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>

{{-- ========== LISTAHAN NG PROMO ========== --}}
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Larawan</th><th class="p-3">Promo</th><th class="p-3">Diskwento</th><th class="p-3">Produkto</th><th class="p-3">Gamit</th><th class="p-3">Bisa</th><th class="p-3">Sa App</th><th class="p-3">Status</th><th class="p-3 text-right">Aksyon</th></tr>
        </thead>
        <tbody>
        @forelse ($promos as $p)
            <tr class="border-t border-espresso/10">
                <td class="p-3">
                    @if ($p->image) <img src="{{ asset('storage/' . $p->image) }}" class="w-16 h-12 rounded-lg object-cover">
                    @else <div class="w-16 h-12 rounded-lg bg-cream flex items-center justify-center text-[10px] text-espresso/40 text-center leading-tight">walang<br>larawan</div> @endif
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
                <td class="p-3">{{ $p->product->name ?? 'Buong order' }}</td>
                <td class="p-3">{{ $p->usages_count }}{{ $p->usage_limit ? ' / ' . $p->usage_limit : '' }}<span class="block text-[11px] text-espresso/50">{{ $p->per_user_limit }}× bawat customer</span></td>
                <td class="p-3 text-xs">
                    {{ $p->starts_at ? $p->starts_at->format('M d') : '—' }} → {{ $p->expires_at ? $p->expires_at->format('M d, Y') : 'walang hanggan' }}
                    @if ($p->isExpired()) <span class="text-red-600">(expired)</span> @endif
                </td>
                <td class="p-3">{{ $p->show_in_app ? '📱 Oo' : '—' }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $p->is_active ? 'active' : 'inactive'])</td>
                <td class="p-3 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('admin.promos.edit', $p) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.promos.destroy', $p) }}" class="inline" onsubmit="return confirm('Burahin ang promo?')">
                        @csrf @method('DELETE') <button class="text-red-600">Burahin</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="p-6 text-center text-espresso/50">Wala pang promo.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
