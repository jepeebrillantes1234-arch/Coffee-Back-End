@extends('layouts.staff')
@section('title', 'Transactions')
@section('content')
@php $typeLabel = ['delivery' => '🛵 Delivery', 'pickup' => '🏪 Pickup', 'walkin' => '🧾 Walk-in']; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5 print:hidden">
    <h1 class="text-2xl font-semibold">Daily Transactions</h1>
    <form method="GET" class="flex gap-2 items-center">
        <input type="date" name="date" value="{{ $date }}" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm bg-white">
        <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">View</button>
        <button type="button" onclick="window.print()" class="bg-white border border-espresso/20 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">🖨 Print</button>
    </form>
</div>

<p class="hidden print:block mb-3 font-semibold text-base">End-of-day Summary · {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach ([
        ['Total Sales', '₱' . number_format($summary['sales'], 2), $summary['count'] . ' orders'],
        ['Counter Cash', '₱' . number_format($summary['counter_cash'], 2), 'walk-in + pickup'],
        ['COD (Riders)', '₱' . number_format($summary['cod'], 2), 'to be collected by rider'],
        ['Discounts', '₱' . number_format($summary['discounts'], 2), $summary['cancelled'] . ' cancelled/rejected · ' . $summary['active'] . ' active'],
    ] as [$l, $v, $s])
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-espresso/5">
            <p class="text-xs text-espresso/60 uppercase tracking-wider font-medium">{{ $l }}</p>
            <p class="text-xl font-bold mt-1 text-espresso">{{ $v }}</p>
            <p class="text-xs text-espresso/50 mt-1">{{ $s }}</p>
        </div>
    @endforeach
</div>

<div class="grid md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-espresso/5">
        <p class="font-semibold text-espresso mb-3">Sales by Order Type</p>
        @forelse ($byType as $type => $x)
            <div class="flex justify-between items-center text-sm py-1.5 border-b border-espresso/5 last:border-0">
                <span class="text-espresso/80">{{ $typeLabel[$type] ?? $type }} ({{ $x['n'] }})</span>
                <span class="font-medium">₱{{ number_format($x['sum'], 2) }}</span>
            </div>
        @empty 
            <p class="text-sm text-espresso/50">No sales recorded yet.</p> 
        @endforelse
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-espresso/5">
        <p class="font-semibold text-espresso mb-3">Walk-in Sales by Cashier</p>
        @forelse ($byCashier as $name => $x)
            <div class="flex justify-between items-center text-sm py-1.5 border-b border-espresso/5 last:border-0">
                <span class="text-espresso/80">{{ $name }} ({{ $x['n'] }})</span>
                <span class="font-medium">₱{{ number_format($x['sum'], 2) }}</span>
            </div>
        @empty 
            <p class="text-sm text-espresso/50">No walk-in transactions yet.</p> 
        @endforelse
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto border border-espresso/5">
    <table class="w-full text-sm text-left">
        <thead class="bg-espresso/5 text-espresso/70 text-xs uppercase tracking-wider">
            <tr>
                <th class="p-3.5">Time</th>
                <th class="p-3.5">Order #</th>
                <th class="p-3.5">Type</th>
                <th class="p-3.5">Customer</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5">Payment</th>
                <th class="p-3.5 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-espresso/10">
        @forelse ($orders as $o)
            <tr class="hover:bg-espresso/[0.02]">
                <td class="p-3.5 whitespace-nowrap text-espresso/70">{{ $o->created_at->format('h:i A') }}</td>
                <td class="p-3.5 font-medium text-espresso">{{ $o->order_number }}</td>
                <td class="p-3.5">{{ $typeLabel[$o->order_type] ?? $o->order_type }}</td>
                <td class="p-3.5">{{ $o->user->name ?? 'Walk-in' }}</td>
                <td class="p-3.5">@include('admin.partials.badge', ['status' => $o->status])</td>
                <td class="p-3.5">{{ strtoupper($o->payment_method) }} · @include('admin.partials.badge', ['status' => $o->payment_status])</td>
                <td class="p-3.5 text-right font-medium">₱{{ number_format($o->total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="p-8 text-center text-espresso/50">No transactions found for this date.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection