@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
@php $max = max($chart->max('total'), 1); @endphp
<h1 class="text-2xl font-semibold mb-6">Dashboard</h1>

<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
    @foreach ([
        ['Benta Ngayon', '₱' . number_format($stats['sales_today'], 2)],
        ['Orders Ngayon', $stats['orders_today']],
        ['Pending Orders', $stats['pending_orders']],
        ['Active Riders', $stats['active_riders']],
        ['Customers', $stats['customers']],
        ['Products', $stats['products']],
    ] as [$label, $value])
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-espresso/60">{{ $label }}</p>
            <p class="text-xl font-semibold mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-3 gap-6 mt-6">
    <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm">
        <p class="font-medium mb-4">Benta (huling 7 araw)</p>
        <div class="flex items-end gap-3 h-48">
            @foreach ($chart as $c)
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <span class="text-[10px] text-espresso/60 mb-1">{{ $c['total'] > 0 ? '₱' . number_format($c['total'], 0) : '' }}</span>
                    <div class="w-full bg-caramel rounded-t-md" style="height: {{ max(($c['total'] / $max) * 100, $c['total'] > 0 ? 4 : 1) }}%"></div>
                    <span class="text-xs mt-2">{{ $c['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm">
        <p class="font-medium mb-4">Top Products (30 araw)</p>
        <ol class="space-y-3 text-sm">
            @forelse ($topProducts as $i => $p)
                <li class="flex justify-between"><span>{{ $i + 1 }}. {{ $p->product_name }}</span><span class="text-espresso/60">{{ $p->qty }} pcs</span></li>
            @empty
                <li class="text-espresso/50">Wala pang benta.</li>
            @endforelse
        </ol>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm mt-6 overflow-hidden">
    <div class="flex justify-between items-center px-6 py-4">
        <p class="font-medium">Kamakailang Orders</p>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-caramel font-medium">Lahat →</a>
    </div>
    <table class="w-full text-sm">
        <tbody>
        @forelse ($recent as $o)
            <tr class="border-t border-espresso/10">
                <td class="px-6 py-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-caramel">{{ $o->order_number }}</a></td>
                <td class="px-3">{{ $o->user->name ?? 'Walk-in' }}</td>
                <td class="px-3">{{ ucfirst($o->order_type) }}</td>
                <td class="px-3">@include('admin.partials.badge', ['status' => $o->status])</td>
                <td class="px-6 text-right">₱{{ number_format($o->total, 2) }}</td>
            </tr>
        @empty
            <tr><td class="px-6 py-6 text-center text-espresso/50">Wala pang order.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
