@extends('layouts.staff')
@section('title', $shift ? 'Close Shift' : 'Open Shift')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div><h1 class="text-2xl font-semibold">{{ $shift ? 'Current Shift' : 'Open a Shift' }}</h1><p class="mt-1 text-sm text-gray-600">{{ $shift ? 'Review the current shift and submit the cash count when closing.' : 'Enter the starting cash fund before opening the staff dashboard.' }}</p></div>
    @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if (!$shift)
        <form method="POST" action="{{ route('staff.shift.open') }}" class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">@csrf
            <label class="block text-sm font-medium">Starting cash fund (PHP)<input name="opening_fund" type="number" min="0" step="0.01" required value="{{ old('opening_fund') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label>
            <button class="rounded-lg bg-gray-900 px-5 py-2.5 font-semibold text-white">Open Shift</button>
        </form>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Opening fund</span><strong class="mt-1 block text-xl">₱{{ number_format($shift->opening_fund, 2) }}</strong></div>
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Opened</span><strong class="mt-1 block">{{ $shift->opened_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</strong></div>
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Cash sales</span><strong class="mt-1 block">₱{{ number_format($summary['cash_sales'], 2) }}</strong></div>
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Cash refunds</span><strong class="mt-1 block">₱{{ number_format($summary['cash_refunds'], 2) }}</strong></div>
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Online / QRPH payments (excluded from drawer)</span><strong class="mt-1 block">₱{{ number_format($summary['online_payments'], 2) }}</strong></div>
            <div class="rounded-xl border bg-white p-4"><span class="text-sm text-gray-500">Expected cash before deductions</span><strong class="mt-1 block">₱{{ number_format((float) $shift->opening_fund + $summary['cash_sales'] - $summary['cash_refunds'], 2) }}</strong></div>
        </div>
        <form method="POST" action="{{ route('staff.shift.close', $shift) }}" class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">@csrf
            <h2 class="text-lg font-semibold">Close Shift and Reconcile Cash</h2><p class="text-sm text-gray-600">Expected cash is the opening fund plus cash sales, less refunds and deductions.</p>
            <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-medium">Actual cash counted (PHP)<input name="actual_cash" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label><label class="block text-sm font-medium">Cash deductions (PHP, if any)<input name="cash_deductions" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label></div>
            <label class="block text-sm font-medium">Deduction reason (required when a deduction is entered)<input name="deduction_reason" maxlength="255" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"></label><button class="rounded-lg bg-gray-900 px-5 py-2.5 font-semibold text-white">Close Shift</button>
        </form>
    @endif
    @if ($recentClosed->isNotEmpty())<section class="rounded-2xl border border-gray-200 bg-white p-5"><h2 class="font-semibold">Recent Closing Reports</h2><ul class="mt-2 divide-y">@foreach ($recentClosed as $closed)<li class="flex items-center justify-between gap-3 py-3"><span>{{ $closed->closed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} · {{ ucfirst($closed->reconciliation_status) }}</span><a class="font-semibold underline" href="{{ route('staff.shift.report', $closed) }}">View / Print</a></li>@endforeach</ul></section>@endif
</div>
@endsection
