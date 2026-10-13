@extends('layouts.admin')
@section('title', 'Banners')
@section('content')
<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">Home Banners</h1>
    <a href="{{ route('admin.banners.create') }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ New Banner</a>
</div>
<p class="text-sm text-espresso/60 mb-6">These banners appear at the top of the Customer App Home screen. If there are no active banners, the automatic "First Order Discount" banner will be used.</p>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Image</th><th class="p-3">Text</th><th class="p-3">Audience</th><th class="p-3">Schedule</th><th class="p-3">Order</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th></tr>
        </thead>
        <tbody>
        @forelse ($banners as $b)
            <tr class="border-t border-espresso/10">
                <td class="p-3">
                    @if ($b->image) <img src="{{ asset('storage/' . $b->image) }}" class="w-20 h-14 rounded-lg object-cover">
                    @else <div class="w-20 h-14 rounded-lg bg-cream flex items-center justify-center text-espresso/40 text-xs">no image</div> @endif
                </td>
                <td class="p-3">
                    <p class="text-xs text-espresso/60">{{ $b->title }}</p>
                    <p class="font-semibold">{{ $b->headline }}</p>
                    <p class="text-xs text-espresso/60">{{ $b->subtitle }}</p>
                </td>
                <td class="p-3">{{ $b->audience === 'new_customers' ? '🆕 New customers only' : '👥 All' }}</td>
                <td class="p-3 text-xs">
                    {{ $b->starts_at ? $b->starts_at->format('M d, Y') : 'immediate' }} → {{ $b->ends_at ? $b->ends_at->format('M d, Y') : 'indefinite' }}
                    @if ($b->ends_at && $b->ends_at->isPast()) <span class="text-red-600">(ended)</span> @endif
                </td>
                <td class="p-3">{{ $b->sort_order }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $b->is_active ? 'active' : 'inactive'])</td>
                <td class="p-3 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('admin.banners.edit', $b) }}" class="text-caramel font-medium">Edit</a>
                    <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" class="inline" onsubmit="return confirm('Delete banner?')">
                        @csrf @method('DELETE') <button class="text-red-600">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-espresso/50">No banners yet. Create your first one!</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection