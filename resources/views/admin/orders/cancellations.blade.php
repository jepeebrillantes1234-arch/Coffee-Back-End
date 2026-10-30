@extends('layouts.admin')
@section('title', 'Cancellation History')
@section('content')
<div class="admin-page-content space-y-5">
    <div>
        <h1 class="text-2xl font-semibold">Cancellation History</h1>
        <p class="mt-1 text-sm text-espresso/60">Customer cancellation details from saved orders and status logs.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($counts as $reason => $count)
            <div class="rounded-xl border border-espresso/10 bg-white p-4 shadow-sm">
                <div class="text-sm text-espresso/60">{{ $reason }}</div>
                <div class="mt-1 text-2xl font-semibold text-espresso">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-espresso/10 bg-white p-4">
        <label class="min-w-48 flex-1 text-xs text-espresso/70">Search
            <input name="search" value="{{ request('search') }}" placeholder="Order, customer, email, or reason" class="mt-1 w-full rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        </label>
        <label class="text-xs text-espresso/70">Reason
            <select name="reason" class="mt-1 rounded-lg border border-espresso/20 px-3 py-2 text-sm">
                <option value="">All reasons</option>
                @foreach (array_keys($counts) as $reason)
                    <option value="{{ $reason }}" @selected(request('reason') === $reason)>{{ $reason }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-espresso/70">From
            <input type="date" name="from" value="{{ request('from') }}" class="mt-1 rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        </label>
        <label class="text-xs text-espresso/70">To
            <input type="date" name="to" value="{{ request('to') }}" class="mt-1 rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        </label>
        <button class="rounded-lg bg-espresso px-4 py-2 text-sm text-cream">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-espresso/10 bg-white shadow-sm">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="bg-espresso/5 text-espresso/70">
                <tr>
                    <th class="p-3">Order / Customer</th>
                    <th class="p-3">Reason / Explanation</th>
                    <th class="p-3">Cancelled at</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3 text-right">Order total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $rawReason = trim((string) $order->reject_reason);
                        $normalizedReason = strtolower($rawReason);
                        $reason = match (true) {
                            str_starts_with($normalizedReason, 'change address'),
                            str_starts_with($normalizedReason, 'change_address') => 'Change Address',
                            str_starts_with($normalizedReason, 'change payment method'),
                            str_starts_with($normalizedReason, 'change_payment_method') => 'Change Payment Method',
                            str_starts_with($normalizedReason, 'wrong order'),
                            str_starts_with($normalizedReason, 'wrong_order') => 'Wrong Order',
                            default => 'Others',
                        };
                        $explanation = $reason === 'Others'
                            ? preg_replace('/^Others\s*:\s*/i', '', $rawReason)
                            : trim(substr($rawReason, strlen($reason)), " :-\t\n\r\0\x0B");
                    @endphp
                    <tr class="border-t border-espresso/10 align-top">
                        <td class="p-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-caramel">{{ $order->order_number }}</a>
                            <div class="mt-1">{{ $order->user->name ?? 'Unknown customer' }}</div>
                            <div class="text-xs text-espresso/60">{{ $order->user->email ?? '' }}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium">{{ $reason }}</div>
                            @if ($explanation !== '')
                                <div class="mt-1 max-w-lg whitespace-pre-wrap text-espresso/70">{{ $explanation }}</div>
                            @endif
                        </td>
                        <td class="p-3">{{ $order->cancelled_at ? \Illuminate\Support\Carbon::parse($order->cancelled_at)->timezone('Asia/Manila')->format('M d, Y h:i A') : '—' }}</td>
                        <td class="p-3">{{ strtoupper($order->payment_method ?? '—') }}<div class="text-xs text-espresso/60">{{ ucfirst($order->payment_status ?? '') }}</div></td>
                        <td class="p-3 text-right">₱{{ number_format((float) $order->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-espresso/50">No cancellations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</div>
@endsection
