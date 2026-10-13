@extends('layouts.admin')
@section('title', 'Deliveries')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Delivery Monitoring</h1>
    <div class="flex gap-2 text-sm">
        <a href="?filter=active" class="px-4 py-2 rounded-lg {{ $filter === 'active' ? 'bg-espresso text-cream' : 'bg-white' }}">Active</a>
        <a href="?filter=all" class="px-4 py-2 rounded-lg {{ $filter === 'all' ? 'bg-espresso text-cream' : 'bg-white' }}">All</a>
        <a href="{{ route('admin.gps.index') }}" class="px-4 py-2 rounded-lg bg-caramel text-white">🗺 Live Map</a>
    </div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Order #</th><th class="p-3">Customer</th><th class="p-3">Address</th><th class="p-3">Rider</th><th class="p-3">Status</th><th class="p-3">Distance</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr class="border-t border-espresso/10">
                <td class="p-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-caramel">{{ $o->order_number }}</a></td>
                <td class="p-3">{{ $o->user->name ?? '—' }}</td>
                <td class="p-3 max-w-xs truncate">{{ $o->delivery_address }}</td>
                <td class="p-3">{{ $o->delivery->rider->name ?? 'No rider yet' }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $o->status])</td>
                <td class="p-3">{{ $o->delivery->distance_km ?? '—' }} {{ optional($o->delivery)->distance_km ? 'km' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-espresso/50">No deliveries found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection