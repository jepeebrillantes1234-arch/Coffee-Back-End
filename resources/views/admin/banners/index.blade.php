@extends('layouts.admin')
@section('title', 'Banners')
@section('content')
<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Home Banners</h1>
    <a href="{{ route('admin.banners.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ Bagong Banner</a>
</div>
<p class="text-sm text-espresso/60 mb-6">Ang mga banner na ito ang lumalabas sa itaas ng Home ng Customer App. Kapag walang aktibong banner, ang awtomatikong "First Order Discount" na banner ang gagamitin.</p>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Larawan</th><th class="p-3">Text</th><th class="p-3">Para kanino</th><th class="p-3">Iskedyul</th><th class="p-3">Ayos</th><th class="p-3">Status</th><th class="p-3 text-right">Aksyon</th></tr>
        </thead>
        <tbody>
        @forelse ($banners as $b)
            <tr class="border-t border-espresso/10">
                <td class="p-3">
                    @if ($b->image) <img src="{{ asset('storage/' . $b->image) }}" class="w-20 h-14 rounded-lg object-cover">
                    @else <div class="w-20 h-14 rounded-lg bg-cream flex items-center justify-center text-espresso/40 text-xs">walang larawan</div> @endif
                </td>
                <td class="p-3">
                    <p class="text-xs text-espresso/60">{{ $b->title }}</p>
                    <p class="font-semibold">{{ $b->headline }}</p>
                    <p class="text-xs text-espresso/60">{{ $b->subtitle }}</p>
                </td>
                <td class="p-3">{{ $b->audience === 'new_customers' ? '🆕 Bagong customer lang' : '👥 Lahat' }}</td>
                <td class="p-3 text-xs">
                    {{ $b->starts_at ? $b->starts_at->format('M d, Y') : 'agad' }} → {{ $b->ends_at ? $b->ends_at->format('M d, Y') : 'walang hanggan' }}
                    @if ($b->ends_at && $b->ends_at->isPast()) <span class="text-red-600">(tapos na)</span> @endif
                </td>
                <td class="p-3">{{ $b->sort_order }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $b->is_active ? 'active' : 'inactive'])</td>
                <td class="p-3 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('admin.banners.edit', $b) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" class="inline" onsubmit="return confirm('Burahin ang banner?')">
                        @csrf @method('DELETE') <button class="text-red-600">Burahin</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-espresso/50">Wala pang banner. Gumawa ng una mo!</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
