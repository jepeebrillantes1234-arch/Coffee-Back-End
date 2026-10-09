@extends('layouts.staff')
@section('title', 'Transactions')
@section('content')
@php $typeLabel = ['delivery' => '🛵 Delivery', 'pickup' => '🏪 Pickup', 'walkin' => '🧾 Walk-in']; @endphp
<div class="flex flex-wrap items-center justify-between gap-3 mb-5 print:hidden">
    <h1 class="text-2xl font-semibold">Daily Transactions</h1>
    <form method="GET" class="flex gap-2 items-center">
        <input type="date" name="date" value="{{ $date }}" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm bg-white">
        <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Tingnan</button>
        <button type="button" onclick="window.print()" class="bg-white border border-espresso/20 px-4 py-2 rounded-lg text-sm">🖨 I-print</button>
    </form>
</div>
<p class="hidden print:block mb-3 font-semibold">End-of-day Summary · {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach ([
        ['Kabuuang Benta', '₱' . number_format($summary['sales'], 2), $summary['count'] . ' order'],
        ['Cash sa Counter', '₱' . number_format($summary['counter_cash'], 2), 'walk-in + pickup'],
        ['COD (riders)', '₱' . number_format($summary['cod'], 2), 'kokolektahin sa rider'],
        ['Diskwento', '₱' . number_format($summary['discounts'], 2), $summary['cancelled'] . ' cancelled/rejected · ' . $summary['active'] . ' aktibo'],
    ] as [$l, $v, $s])
        <div class="bg-white rounded-2xl p-5 shadow-sm"><p class="text-xs text-espresso/60">{{ $l }}</p><p class="text-xl font-semibold mt-1">{{ $v }}</p><p class="text-xs text-espresso/50 mt-1">{{ $s }}</p></div>
    @endforeach
</div>

<div class="grid md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="font-medium mb-2">Ayon sa uri ng order</p>
        @forelse ($byType as $type => $x)
            <div class="flex justify-between text-sm py-1"><span>{{ $typeLabel[$type] ?? $type }} ({{ $x['n'] }})</span><span>₱{{ number_format($x['sum'], 2) }}</span></div>
        @empty <p class="text-sm text-espresso/50">Wala pang benta.</p> @endforelse
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="font-medium mb-2">Walk-in ayon sa cashier</p>
        @forelse ($byCashier as $name => $x)
            <div class="flex justify-between text-sm py-1"><span>{{ $name }} ({{ $x['n'] }})</span><span>₱{{ number_format($x['sum'], 2) }}</span></div>
        @empty <p class="text-sm text-espresso/50">Wala pang walk-in.</p> @endforelse
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Oras</th><th class="p-3">Order #</th><th class="p-3">Uri</th><th class="p-3">Customer</th><th class="p-3">Status</th><th class="p-3">Bayad</th><th class="p-3 text-right">Total</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr class="border-t border-espresso/10">
                <td class="p-3 whitespace-nowrap">{{ $o->created_at->format('h:i A') }}</td>
                <td class="p-3 font-medium">{{ $o->order_number }}</td>
                <td class="p-3">{{ $typeLabel[$o->order_type] ?? $o->order_type }}</td>
                <td class="p-3">{{ $o->user->name ?? 'Walk-in' }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $o->status])</td>
                <td class="p-3">{{ strtoupper($o->payment_method) }} · @include('admin.partials.badge', ['status' => $o->payment_status])</td>
                <td class="p-3 text-right">₱{{ number_format($o->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-6 text-center text-espresso/50">Walang transaksyon sa araw na ito.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
