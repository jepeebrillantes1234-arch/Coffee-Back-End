@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <h1 class="text-2xl font-semibold">Reports</h1>
    <div class="flex gap-2 text-sm">
        @foreach (['sales' => 'Sales', 'products' => 'Products', 'riders' => 'Riders', 'customers' => 'Customers'] as $k => $l)
            <a href="{{ route('admin.reports.show', ['type' => $k, 'from' => $from, 'to' => $to]) }}"
               class="px-4 py-2 rounded-lg {{ $type === $k ? 'bg-espresso text-cream' : 'bg-white' }}">{{ $l }}</a>
        @endforeach
    </div>
</div>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-4 print:hidden">
    <div><label class="block text-xs mb-1">From</label><input type="date" name="from" value="{{ $from }}" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm"></div>
    <div><label class="block text-xs mb-1">To</label><input type="date" name="to" value="{{ $to }}" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm"></div>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Apply</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm">⬇ Export (Excel/CSV)</a>
    <button type="button" onclick="window.print()" class="bg-white border border-espresso/20 px-4 py-2 rounded-lg text-sm">🖨 Print / Save as PDF</button>
</form>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <div class="px-6 pt-5 pb-2"><p class="font-semibold">{{ $title }}</p><p class="text-xs text-espresso/50">{{ $from }} to {{ $to }} · delivered/completed orders only</p></div>
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr>@foreach ($columns as $c) <th class="p-3">{{ $c }}</th> @endforeach</tr>
        </thead>
        <tbody>
        @forelse ($rows as $r)
            <tr class="border-t border-espresso/10">@foreach ($r as $cell) <td class="p-3">{{ $cell }}</td> @endforeach</tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="p-6 text-center text-espresso/50">No data found for this date range.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection