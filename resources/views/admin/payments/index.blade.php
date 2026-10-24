@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-2xl font-semibold">Transaction History</h1><p class="mt-1 text-sm text-espresso/60">Online payments and cash/COD records from the existing payment table.</p></div><a href="{{ route('admin.payments.settings') }}" class="rounded-lg border px-4 py-2">Payment methods settings</a></div>
<div id="admin-payments-live">
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    @foreach ([['Paid today', $summary['paid_today']], ['Pending', $summary['pending']], ['Refunded', $summary['refunded']]] as [$label, $value])
        <div class="rounded-2xl bg-white p-5 shadow-sm"><p class="text-xs text-espresso/60">{{ $label }}</p><p class="mt-1 text-xl font-semibold">₱{{ number_format($value, 2) }}</p></div>
    @endforeach
</div>
<form method="GET" class="mb-4 grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
    <input name="q" value="{{ request('q') }}" placeholder="Transaction, order, customer" class="min-w-0 rounded-lg border px-3 py-2 text-sm lg:col-span-2">
    <select name="method" class="rounded-lg border px-3 py-2 text-sm"><option value="">All methods</option>@foreach(['qrph','cash','cod'] as $method)<option value="{{ $method }}" @selected(request('method')===$method)>{{ strtoupper($method) }}</option>@endforeach</select>
    <select name="status" class="rounded-lg border px-3 py-2 text-sm"><option value="">All statuses</option>@foreach(['pending','paid','failed','expired','refunded'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}" aria-label="From date" class="rounded-lg border px-3 py-2 text-sm">
    <input type="date" name="to" value="{{ request('to') }}" aria-label="To date" class="rounded-lg border px-3 py-2 text-sm">
    <div class="flex gap-2 lg:col-span-6"><button class="rounded-lg bg-espresso px-4 py-2 text-sm text-cream">Filter</button><a href="{{ route('admin.payments.index') }}" class="rounded-lg border px-4 py-2 text-sm">Clear</a></div>
</form>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full min-w-[900px] text-left text-sm">
    <thead class="bg-espresso/5 text-espresso/70"><tr><th class="p-3">Transaction ID</th><th class="p-3">Order #</th><th class="p-3">Customer</th><th class="p-3">Method</th><th class="p-3 text-right">Amount</th><th class="p-3">Status</th><th class="p-3">Created</th><th class="p-3">Paid</th><th class="p-3">Action</th></tr></thead>
    <tbody>
    @forelse($payments as $payment)
        @php($order = $payment->order)
        @php($status = $order?->payment_status === 'refunded' ? 'refunded' : ($order?->payment_status === 'paid' ? 'paid' : $payment->status))
        <tr class="border-t border-espresso/10"><td class="p-3 font-mono text-xs">{{ $payment->provider_payment_id ?: ($payment->reference_no ?: 'PAY-'.$payment->id) }}</td><td class="p-3"><a class="font-medium text-caramel" href="{{ route('admin.orders.show', $order) }}">{{ $order?->order_number ?? '—' }}</a></td><td class="p-3">{{ $order?->user?->name ?? 'Walk-in' }}</td><td class="p-3">{{ strtoupper($payment->method) }}</td><td class="p-3 text-right">₱{{ number_format((float)$payment->amount, 2) }}</td><td class="p-3">@include('admin.partials.badge', ['status' => $status])</td><td class="p-3">{{ $payment->created_at?->format('M j, Y g:i A') ?? '—' }}</td><td class="p-3">{{ $payment->paid_at?->format('M j, Y g:i A') ?? '—' }}</td><td class="p-3"><div class="flex gap-3">@if($order?->payment_status==='unpaid' && in_array($order?->payment_method,['cash','cod'],true))<form method="POST" action="{{ route('admin.payments.paid',$order) }}">@csrf<button class="text-success">Mark paid</button></form>@elseif($order?->payment_status==='paid')<form method="POST" action="{{ route('admin.payments.refund',$order) }}" onsubmit="return confirm('Refund this payment?')">@csrf<button class="text-red-600">Refund</button></form>@endif</div></td></tr>
    @empty<tr><td colspan="9" class="p-6 text-center text-espresso/50">No transaction records match these filters.</td></tr>@endforelse
    </tbody>
</table></div>
<div class="mt-4">{{ $payments->links() }}</div>
</div>
@push('scripts')
<script>window.addEventListener('live-update',async e=>{if(!(['order','delivery','payment'].includes(e.detail?.kind)))return;try{const r=await fetch(location.href,{headers:{'X-Requested-With':'XMLHttpRequest'}});if(!r.ok)return;const d=new DOMParser().parseFromString(await r.text(),'text/html'),n=d.getElementById('admin-payments-live'),c=document.getElementById('admin-payments-live');if(n&&c)c.innerHTML=n.innerHTML;}catch(_){}});</script>
@endpush
@endsection
