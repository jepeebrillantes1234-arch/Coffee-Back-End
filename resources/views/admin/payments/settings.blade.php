@extends('layouts.admin')
@section('title', 'Payment Methods')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-semibold">Payment Methods</h1><p class="mt-1 text-sm text-espresso/60">Manage availability for the existing PayMongo QRPh checkout.</p></div>
    <a href="{{ route('admin.payments.index') }}" class="rounded-lg border px-4 py-2">Transaction history</a>
</div>
<section class="max-w-3xl rounded-2xl bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
        <div><h2 class="font-semibold">Online payment gateway</h2><p class="mt-1 text-sm text-espresso/60">PayMongo</p></div>
        @if($simulated)<span class="rounded-full bg-amber-100 px-3 py-1 text-sm text-amber-800">Local simulation mode</span>
        @elseif($gatewayConfigured)<span class="rounded-full bg-emerald-100 px-3 py-1 text-sm text-emerald-800">Configured</span>
        @else<span class="rounded-full bg-rose-100 px-3 py-1 text-sm text-rose-800">Not configured</span>@endif
    </div>
    <div class="py-4"><h3 class="font-medium">Supported online method</h3><p class="mt-1 text-sm text-espresso/60">QRPh checkout hosted by PayMongo. Other PayMongo methods are not enabled by this application's checkout integration.</p></div>
    <form method="POST" action="{{ route('admin.payments.settings.update') }}" class="flex flex-wrap items-center justify-between gap-4 border-t pt-4">
        @csrf @method('PUT')
        <label class="flex items-center gap-3"><input type="checkbox" name="qrph_enabled" value="1" @checked($qrphEnabled) @disabled(!$qrphAvailable)><span><strong>QRPh</strong><small class="block text-espresso/60">{{ $qrphAvailable ? ($qrphEnabled ? 'Enabled for customer checkout' : 'Disabled for new checkouts') : 'Unavailable: enable online checkout and configure PayMongo first' }}</small></span></label>
        <button @disabled(!$qrphAvailable) class="rounded-lg bg-espresso px-4 py-2 text-white disabled:opacity-50">Save settings</button>
    </form>
    <p class="mt-5 rounded-lg bg-espresso/5 p-3 text-sm text-espresso/70">Cash and Cash on Delivery are collected through the existing counter, rider proof, and staff remittance flow. Delivery status by itself does not create a new cash collection record.</p>
</section>
@endsection
