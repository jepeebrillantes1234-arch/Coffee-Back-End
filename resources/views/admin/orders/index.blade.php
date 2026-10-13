@extends('layouts.admin')
@section('title', 'Orders')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Orders</h1>
<form method="GET" class="flex flex-wrap gap-3 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Order number..." class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
    <select name="status" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">All statuses</option>
        @foreach (['awaiting_payment','pending','confirmed','preparing','ready','rider_assigned','picked_up','on_the_way','delivered','completed','rejected','cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
        @endforeach
    </select>
    <select name="order_type" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">All types</option>
        @foreach (['delivery' => 'Delivery', 'pickup' => 'Pickup', 'walkin' => 'Walk-in'] as $k => $l)
            <option value="{{ $k }}" @selected(request('order_type') === $k)>{{ $l }}</option>
        @endforeach
    </select>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Filter</button>
</form>
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Order #</th><th class="p-3">Customer</th><th class="p-3">Type</th><th class="p-3">Status</th><th class="p-3">Payment</th><th class="p-3 text-right">Total</th><th class="p-3">Date</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr class="border-t border-espresso/10 hover:bg-cream/40">
                <td class="p-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-caramel">{{ $o->order_number }}</a></td>
                <td class="p-3">{{ $o->user->name ?? 'Walk-in' }}</td>
                <td class="p-3">{{ ucfirst($o->order_type) }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $o->status])</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $o->payment_status])</td>
                <td class="p-3 text-right">₱{{ number_format($o->total, 2) }}</td>
                <td class="p-3 text-xs text-espresso/60">{{ $o->created_at->format('M d, h:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-espresso/50">No orders found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection