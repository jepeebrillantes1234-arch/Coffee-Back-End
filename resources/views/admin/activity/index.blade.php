@extends('layouts.admin')
@section('title', 'Activity Logs')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Activity Logs</h1>
<form method="GET" class="flex gap-3 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Hanapin..." class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-64">
    <select name="module" class="rounded-lg border border-espresso/20 px-3 py-2 text-sm">
        <option value="">Lahat ng module</option>
        @foreach ($modules as $m) <option value="{{ $m }}" @selected(request('module') === $m)>{{ $m }}</option> @endforeach
    </select>
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">I-filter</button>
</form>
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr><th class="p-3">Kailan</th><th class="p-3">Sino</th><th class="p-3">Module</th><th class="p-3">Aksyon</th><th class="p-3">Detalye</th><th class="p-3">IP</th></tr>
        </thead>
        <tbody>
        @forelse ($logs as $l)
            <tr class="border-t border-espresso/10">
                <td class="p-3 whitespace-nowrap text-xs">{{ $l->created_at->format('M d, h:i A') }}</td>
                <td class="p-3">{{ $l->user->name ?? '—' }} <span class="text-xs text-espresso/40">{{ $l->user->role ?? '' }}</span></td>
                <td class="p-3">{{ $l->module }}</td>
                <td class="p-3">{{ $l->action }}</td>
                <td class="p-3">{{ $l->description }}</td>
                <td class="p-3 text-xs text-espresso/50">{{ $l->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-espresso/50">Wala pang log.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
