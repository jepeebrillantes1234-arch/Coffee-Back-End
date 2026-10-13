@extends('layouts.admin')
@php $labels = ['customer' => 'Customers', 'staff' => 'Staff', 'rider' => 'Riders']; @endphp
@section('title', $labels[$role])
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">{{ $labels[$role] }}</h1>
    @if ($role !== 'customer')
        <a href="{{ route('admin.users.create', $role) }}" class="bg-caramel text-white px-4 py-2 rounded-lg text-sm font-medium">+ Add New</a>
    @endif
</div>

<form method="GET" class="flex gap-3 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Name, email, or phone..." class="rounded-lg border border-espresso/20 px-3 py-2 text-sm w-72">
    <button class="bg-espresso text-cream px-4 py-2 rounded-lg text-sm">Search</button>
</form>

<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70">
            <tr>
                <th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Phone</th>
                @if ($role === 'customer') <th class="p-3">Orders</th> @endif
                @if ($role === 'rider') <th class="p-3">Vehicle</th><th class="p-3">Approved</th><th class="p-3">Online</th><th class="p-3">Trips</th> @endif
                <th class="p-3">Status</th><th class="p-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($users as $u)
            <tr class="border-t border-espresso/10">
                <td class="p-3 font-medium">{{ $u->name }}</td>
                <td class="p-3">{{ $u->email }}</td>
                <td class="p-3">{{ $u->phone ?? '—' }}</td>
                @if ($role === 'customer') <td class="p-3">{{ $u->orders_count }}</td> @endif
                @if ($role === 'rider')
                    <td class="p-3">{{ $u->riderProfile->vehicle_type ?? '—' }} {{ $u->riderProfile->plate_number ?? '' }}</td>
                    <td class="p-3">
                        <form method="POST" action="{{ route('admin.users.approve', $u) }}">@csrf
                            <button class="text-xs px-2 py-1 rounded-full {{ optional($u->riderProfile)->is_approved ? 'bg-success/10 text-success' : 'bg-amber-100 text-amber-700' }}">
                                {{ optional($u->riderProfile)->is_approved ? 'Approved' : 'Approve' }}
                            </button>
                        </form>
                    </td>
                    <td class="p-3">{{ optional($u->riderProfile)->is_available ? '🟢' : '⚪' }}</td>
                    <td class="p-3">{{ $u->deliveries_count }}</td>
                @endif
                <td class="p-3">@include('admin.partials.badge', ['status' => $u->status])</td>
                <td class="p-3 text-right space-x-3 whitespace-nowrap">
                    @if ($role !== 'customer') <a href="{{ route('admin.users.edit', $u) }}" class="text-caramel font-medium">Edit</a> @endif
                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="inline">@csrf
                        <button class="{{ $u->status === 'active' ? 'text-red-600' : 'text-success' }}">{{ $u->status === 'active' ? 'Block' : 'Unblock' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="p-6 text-center text-espresso/50">No records found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection