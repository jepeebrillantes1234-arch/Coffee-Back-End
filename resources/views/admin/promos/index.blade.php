@extends('layouts.admin')
@section('title', 'Promos')
@section('content')
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-caramel'; @endphp

<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Promos</h1>
    <a href="{{ route('admin.promos.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ New Promo</a>
</div>
<p class="mb-4"><a class="text-caramel font-medium" href="{{ route('admin.home-promos.index') }}">Manage Home Bundles &amp; Best Sellers →</a></p>
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
            <div class="flex flex-col items-start gap-2">
                @if ($fo['image'])
                    <div class="h-20 w-20 overflow-hidden rounded-xl bg-cream"><img src="{{ asset('storage/' . $fo['image']) }}" alt="First order promotion" class="h-full w-full object-cover"></div>
                @endif
                <input type="file" name="fo_image" accept="image/*" class="w-full text-xs">
            </div>
            @if ($fo['image']) <label class="text-xs flex items-center gap-1 mt-1"><input type="checkbox" name="remove_fo_image" value="1"> Remove image</label> @endif
        </div>
        <div class="md:col-span-4"><button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Save First Order banner</button></div>
    </form>
    @error('fo_image') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
</div>

{{-- ========== PROMO LIST ========== --}}
<div class="grid gap-3 xl:hidden">
    @forelse ($promos as $p)
        <article class="flex min-w-0 items-start gap-3 rounded-2xl border border-espresso/10 bg-white p-3 shadow-sm sm:gap-4 sm:p-4">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-cream sm:h-24 sm:w-24">
                @if ($p->image)
                    <img src="{{ asset('storage/' . $p->image) }}" alt="{{ $p->title ?: $p->code }}" loading="lazy" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-center text-xs text-espresso/40">No image</div>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 class="font-semibold tracking-wide">{{ $p->code }}</h2>
                    @include('admin.partials.badge', ['status' => $p->is_active ? 'active' : 'inactive'])
                </div>
                <p class="mt-1 break-words text-sm text-espresso/70">{{ $p->headline ?: $p->title ?: 'Promotion' }}</p>
                <p class="mt-2 text-sm font-medium">
                    @if ($p->type === 'bogo') Buy {{ $p->buy_qty }} Get {{ $p->free_qty }} Free
                    @elseif ($p->type === 'percent') {{ rtrim(rtrim(number_format($p->value, 2), '0'), '.') }}%
                    @else â‚±{{ number_format($p->value, 2) }} @endif
                    <span class="font-normal text-espresso/60">Â· {{ $p->product->name ?? 'Whole order' }}</span>
                </p>
                <p class="mt-1 text-xs text-espresso/60">{{ $p->show_in_app ? 'Shown in app' : 'Hidden in app' }} Â· {{ $p->usages_count }}{{ $p->usage_limit ? ' / ' . $p->usage_limit : '' }} uses</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-espresso/10 pt-2 text-sm">
                    <a href="{{ route('admin.promos.edit', $p) }}" class="font-medium text-caramel">Edit</a>
                    <form method="POST" action="{{ route('admin.promos.destroy', $p) }}" onsubmit="return confirm('Delete promo?')">
                        @csrf @method('DELETE')
                        <button class="font-medium text-red-600">Delete</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center text-espresso/50">No promos found.</div>
    @endforelse
</div>

<div class="hidden xl:block bg-white rounded-2xl shadow-sm overflow-x-auto">
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
