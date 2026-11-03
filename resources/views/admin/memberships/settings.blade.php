@extends('layouts.admin')
@section('title', 'Membership Settings')
@section('content')
@php($price = $s['membership_card_price'] ?? '100.00')
@php($percent = $s['membership_discount_percent'] ?? '10')
@php($enabled = ($s['membership_discount_enabled'] ?? '1') === '1')
<div class="mx-auto max-w-5xl space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-espresso">Membership Settings</h1>
        <p class="mt-1 text-sm text-espresso/65">Manage future membership purchases and member order discounts.</p>
    </header>

    <section class="grid gap-4 sm:grid-cols-3" aria-label="Membership overview">
        <div class="rounded-2xl border border-espresso/10 bg-white p-5"><p class="text-sm text-espresso/60">All memberships</p><p class="mt-2 text-3xl font-bold">{{ number_format($overview['members']) }}</p></div>
        <div class="rounded-2xl border border-espresso/10 bg-white p-5"><p class="text-sm text-espresso/60">Active members</p><p class="mt-2 text-3xl font-bold">{{ number_format($overview['active']) }}</p></div>
        <div class="rounded-2xl border border-espresso/10 bg-white p-5"><p class="text-sm text-espresso/60">Pending membership payments</p><p class="mt-2 text-3xl font-bold">{{ number_format($overview['pending_payments']) }}</p></div>
    </section>

    <form method="POST" action="{{ route('admin.memberships.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <section class="rounded-2xl border border-espresso/10 bg-white p-5 sm:p-7">
            <h2 class="text-lg font-semibold">Membership Card Price</h2>
            <p class="mt-1 text-sm text-espresso/60">Changes apply to newly created membership payment records. Existing payments keep their saved amount.</p>
            <label for="membership_card_price" class="mt-5 block text-sm font-medium">Price (PHP)</label>
            <div class="mt-1 flex max-w-sm items-center rounded-xl border border-espresso/20 bg-cream px-3"><span class="text-espresso/70">₱</span><input id="membership_card_price" name="membership_card_price" type="number" min="0.01" max="100000" step="0.01" required value="{{ old('membership_card_price', $price) }}" class="w-full border-0 bg-transparent px-2 py-3 focus:ring-0"></div>
            @error('membership_card_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-2xl border border-espresso/10 bg-white p-5 sm:p-7">
            <h2 class="text-lg font-semibold">Member Discount Settings</h2>
            <p class="mt-1 text-sm text-espresso/60">Laravel verifies active membership and calculates this discount for eligible products.</p>
            <label for="membership_discount_percent" class="mt-5 block text-sm font-medium">Discount percentage</label>
            <div class="mt-1 flex max-w-sm items-center rounded-xl border border-espresso/20 bg-cream px-3"><input id="membership_discount_percent" name="membership_discount_percent" type="number" min="0" max="100" step="0.01" required value="{{ old('membership_discount_percent', $percent) }}" class="w-full border-0 bg-transparent px-2 py-3 focus:ring-0"><span class="text-espresso/70">%</span></div>
            @error('membership_discount_percent')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <label class="mt-5 inline-flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="membership_discount_enabled" value="1" @checked(old('membership_discount_enabled', $enabled)) class="rounded border-espresso/30 text-caramel focus:ring-caramel"> Enable member discount</label>
        </section>

        <section class="rounded-2xl border border-espresso/10 bg-cream/70 p-5 sm:p-7">
            <h2 class="text-lg font-semibold">Membership Rules</h2>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-espresso/75">
                <li>Only active, unexpired memberships qualify.</li>
                <li>Full-price standalone products qualify; sale-priced products, bundles, BOGO free units, and delivery fees do not.</li>
                <li>A product already discounted by a promo code or first-order discount does not receive an additional member discount.</li>
                <li>Order totals and discount snapshots are saved when the order is placed.</li>
            </ul>
        </section>
        <button type="submit" class="rounded-xl bg-espresso px-5 py-3 font-semibold text-white hover:bg-coffee">Save Membership Settings</button>
    </form>
</div>
@endsection
