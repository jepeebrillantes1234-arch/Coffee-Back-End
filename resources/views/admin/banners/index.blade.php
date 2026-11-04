@extends('layouts.admin')
@section('title', $section === 'popups' ? 'Popup Promotions' : 'Home Banners')
@section('content')
<div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-semibold">{{ $section === 'popups' ? 'Popup Promotions' : 'Home Banners' }}</h1>
    <a href="{{ route('admin.banners.create', ['section' => $section]) }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">{{ $section === 'popups' ? '+ New Popup Promotion' : '+ New Home Banner' }}</a>
</div>
<p class="text-sm text-espresso/60 mb-4">{{ $section === 'popups' ? 'Active, scheduled Membership and Promo records appear as an independent overlay on Home. They are kept out of the Home Banner Carousel.' : 'Active, scheduled records in this section appear in display order in the Customer App Home carousel. The API returns at most five. Promo and promo-code banners open the existing Promotions page and never apply a promo automatically.' }}</p>
<nav class="flex gap-2 mb-6" aria-label="Banner management sections">
    <a href="{{ route('admin.banners.index', ['section' => 'home']) }}" class="rounded-lg px-4 py-2 text-sm font-medium {{ $section === 'home' ? 'bg-espresso text-cream' : 'border border-espresso/20' }}">Home Banners</a>
    <a href="{{ route('admin.banners.index', ['section' => 'popups']) }}" class="rounded-lg px-4 py-2 text-sm font-medium {{ $section === 'popups' ? 'bg-espresso text-cream' : 'border border-espresso/20' }}">Popup Promotions</a>
</nav>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
    <tr><th class="p-3">Image</th><th class="p-3">Text</th><th class="p-3">Type</th><th class="p-3">Destination</th><th class="p-3">Audience</th><th class="p-3">Schedule</th><th class="p-3">Order</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th></tr>
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
                    <p class="text-xs text-espresso/60">{{ $b->description }}</p>
                    @if ($b->button_text) <p class="text-xs text-caramel">Button: {{ $b->button_text }}</p> @endif
                </td>
                <td class="p-3">{{ str($b->banner_type)->replace('_', ' ')->title() }}</td>
                <td class="p-3">{{ str($b->destination)->title() }}</td>
                <td class="p-3">{{ $b->audience === 'new_customers' ? '🆕 New customers only' : '👥 All' }}</td>
                <td class="p-3 text-xs">
                    {{ $b->starts_at ? $b->starts_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') : 'immediate' }} to {{ $b->ends_at ? $b->ends_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') : 'indefinite' }}
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
            <tr><td colspan="9" class="p-6 text-center text-espresso/50">{{ $section === 'popups' ? 'No popup promotions yet. Create your first one!' : 'No home banners yet. Create your first one!' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
