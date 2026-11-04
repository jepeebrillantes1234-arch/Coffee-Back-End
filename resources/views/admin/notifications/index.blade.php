@extends('layouts.admin')
@section('title', 'Notification History')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div><h1 class="text-2xl font-semibold">Notification History</h1><p class="text-sm text-espresso/60 mt-1">{{ $unreadCount }} unread notification(s). Open a record to view details.</p></div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-espresso/5 text-left text-espresso/70"><tr><th class="p-3">Notification</th><th class="p-3">Related record</th><th class="p-3">Received</th><th class="p-3">Status</th><th class="p-3">Details</th></tr></thead>
        <tbody>
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <tr class="border-t border-espresso/10 {{ $notification->is_read ? '' : 'font-semibold' }}">
                <td class="p-3"><div>{{ $notification->title }}</div><div class="text-xs text-espresso/60 font-normal">{{ \Illuminate\Support\Str::limit($notification->message, 150) }}</div></td>
                <td class="p-3 font-normal">{{ $notification->related_label ?? ($data['order_number'] ?? '—') }}</td>
                <td class="p-3 whitespace-nowrap font-normal">{{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('M d, Y h:i A') }}</td>
                <td class="p-3"><span class="rounded-full px-2 py-1 text-xs {{ $notification->is_read ? 'bg-espresso/10 text-espresso/60' : 'bg-amber-100 text-amber-900' }}">{{ $notification->is_read ? 'Read' : 'Unread' }}</span>@if($notification->is_read)<div class="text-xs text-espresso/50 mt-1">{{ \Illuminate\Support\Carbon::parse($notification->updated_at)->format('M d, h:i A') }}</div>@endif</td>
                <td class="p-3"><a class="text-espresso underline" href="{{ route('admin.notifications.show', $notification->id) }}">View details</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-6 text-center text-espresso/50">No notifications yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
