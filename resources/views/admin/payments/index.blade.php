@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Payments</h1>
<div id="admin-payments-live"><div class="grid sm:grid-cols-3 gap-4 mb-6">
    @foreach ([['Paid today', $summary['paid_today']], ['Unpaid', $summary['unpaid']], ['Refunded', $summary['refunded']]] as [$l, $v])
        <div class="bg-white rounded-2xl p-5 shadow-sm"><p class="text-xs text-espresso/60">{{ $l }}</p><p class="text-xl font-semibold mt-1">₱{{ number_format($v, 2) }}</p></div>
    @endforeach
</div>
<form method="GET" class="flex gap-3 mb-4">
    <select name="payment_status" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">All statuses</option>
        @foreach (['paid', 'unpaid', 'refunded'] as $s) <option value="{{ $s }}" @selected(request('payment_status') === $s)>{{ ucfirst($s) }}</option> @endforeach
    </select>
    <select name="payment_method" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">All methods</option>
        @foreach (['cash', 'cod', 'gcash', 'card'] as $s) <option value="{{ $s }}" @selected(request('payment_method') === $s)>{{ strtoupper($s) }}</option> @endforeach
    </select>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Filter</button>
</form>
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Order #</th><th class="p-3">Customer</th><th class="p-3">Method</th><th class="p-3">Status</th><th class="p-3 text-right">Amount</th><th class="p-3 text-right">Action</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr class="border-t border-espresso/10">
                <td class="p-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-caramel">{{ $o->order_number }}</a></td>
                <td class="p-3">{{ $o->user->name ?? 'Walk-in' }}</td>
                <td class="p-3">{{ strtoupper($o->payment_method) }}</td>
                <td class="p-3">@include('admin.partials.badge', ['status' => $o->payment_status])</td>
                <td class="p-3 text-right">₱{{ number_format($o->total, 2) }}</td>
                <td class="p-3 text-right">
                    @if ($o->payment_status === 'unpaid')
                        <form method="POST" action="{{ route('admin.payments.paid', $o) }}" class="inline">@csrf <button class="text-success font-medium">Mark as Paid</button></form>
                    @elseif ($o->payment_status === 'paid')
                        <form method="POST" action="{{ route('admin.payments.refund', $o) }}" class="inline" onsubmit="return confirm('Refund this payment?')">@csrf <button class="text-red-600">Refund</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-espresso/50">No payment records found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div></div>
@push('scripts')
<script>window.addEventListener('live-update', async e => { if (!(['order','delivery','payment'].includes(e.detail?.kind))) return; try { const r=await fetch(location.href,{headers:{'X-Requested-With':'XMLHttpRequest'}}); if(!r.ok)return; const d=new DOMParser().parseFromString(await r.text(),'text/html'); {const n=d.getElementById('admin-payments-live'),c=document.getElementById('admin-payments-live');if(n&&c)c.innerHTML=n.innerHTML;} } catch(_){} });</script>
@endpush
@endsection