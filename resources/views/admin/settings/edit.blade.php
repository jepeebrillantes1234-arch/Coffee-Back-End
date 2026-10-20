@extends('layouts.admin')
@section('title', 'Discount & Fees')
@section('content')
<h1 class="text-2xl font-semibold mb-6">First Order Discount and Delivery Settings</h1>
@php
    $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel';
    $v = fn ($k, $d = '') => old($k, $s[$k] ?? $d);
@endphp
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6 max-w-2xl">
    @csrf @method('PUT')

    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        <p class="font-semibold">ðŸŽ First Order Discount</p>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="first_order_enabled" value="1" @checked($v('first_order_enabled', '1') == '1')> Enabled</label>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-sm mb-1">Discount (%)</label><input type="number" step="0.01" name="first_order_percent" value="{{ $v('first_order_percent', 15) }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Maximum discount (â‚±)</label><input type="number" step="0.01" name="first_order_max" value="{{ $v('first_order_max', 100) }}" class="{{ $in }}"></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_promo_stacking" value="1" @checked($v('allow_promo_stacking', '0') == '1')> Allow promo codes to stack with first order discount</label>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        <p class="font-semibold">ðŸ“ Shop Location</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-sm mb-1">Latitude</label><input name="shop_lat" value="{{ $v('shop_lat') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Longitude</label><input name="shop_lng" value="{{ $v('shop_lng') }}" class="{{ $in }}"></div>
        </div>
        <p class="text-xs text-espresso/50">Tip: on Google Maps, right-click your shop and click the coordinates to copy them.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        <p class="font-semibold">ðŸ›µ Delivery</p>
        <div class="grid sm:grid-cols-3 gap-4">
            <div><label class="block text-sm mb-1">Base fee (â‚±)</label><input type="number" step="0.01" name="delivery_base_fee" value="{{ $v('delivery_base_fee', 30) }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Per km (â‚±)</label><input type="number" step="0.01" name="delivery_fee_per_km" value="{{ $v('delivery_fee_per_km', 10) }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Radius (km)</label><input type="number" step="0.1" name="delivery_radius_km" value="{{ $v('delivery_radius_km', 8) }}" class="{{ $in }}"></div>
        </div>
        <div class="w-48"><label class="block text-sm mb-1">Auto-cancel if unconfirmed (minutes)</label><input type="number" name="auto_cancel_minutes" value="{{ $v('auto_cancel_minutes', 10) }}" class="{{ $in }}"></div>
    </div>

    <div id="customer-service" class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        <div>
            <p class="font-semibold">Customer Service Contact</p>
            <p class="text-sm text-espresso/60">These details appear in the customer app profile and login help screen.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-sm mb-1">Display name</label><input name="support_name" value="{{ $v('support_name', 'KJM Coffee Shop Customer Service') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Phone number</label><input name="support_phone" type="tel" value="{{ $v('support_phone') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">Support email</label><input name="support_email" type="email" value="{{ $v('support_email') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm mb-1">WhatsApp number</label><input name="support_whatsapp" type="tel" value="{{ $v('support_whatsapp') }}" class="{{ $in }}"></div>
            <div class="sm:col-span-2"><label class="block text-sm mb-1">Support hours</label><input name="support_hours" value="{{ $v('support_hours') }}" placeholder="Example: Monday–Saturday, 8:00 AM–6:00 PM" class="{{ $in }}"></div>
        </div>
    </div>
    @if ($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
    @endif
    <button class="bg-espresso text-cream px-6 py-2.5 rounded-lg font-medium">Save Settings</button>
</form>
@endsection
