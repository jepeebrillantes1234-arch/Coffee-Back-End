@extends('layouts.admin')
@section('title', 'Support Conversation')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('admin.support.index') }}" class="text-caramel">Back to inbox</a>
        <div class="mt-2 flex items-center gap-3">
            @if($conversation->user?->avatar)
                <img src="{{ asset('storage/' . ltrim($conversation->user->avatar, '/')) }}" alt="Customer profile photo" class="h-12 w-12 rounded-full object-cover ring-2 ring-white shadow-sm">
            @else
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-lg font-semibold text-espresso">{{ strtoupper(substr($conversation->user->name ?? $conversation->guest_name ?? 'C', 0, 1)) }}</div>
            @endif
            <div>
                <h1 class="text-2xl font-semibold">{{ $conversation->user->name ?? $conversation->guest_name ?? 'Guest customer' }}</h1>
                <p class="text-sm text-espresso/60">{{ $conversation->user->email ?? $conversation->guest_contact ?? 'No contact supplied' }} · {{ ucfirst(str_replace('_', ' ', $conversation->status)) }}@if($conversation->user?->role) · {{ ucfirst($conversation->user->role) }}@endif</p>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('admin.support.status', $conversation) }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <label class="sr-only" for="ticket-status">Ticket status</label>
            <select id="ticket-status" name="status" class="rounded-lg border border-espresso/15 px-3 py-2 text-sm">
                @foreach(['open' => 'Open', 'waiting_admin' => 'Waiting for admin', 'closed' => 'Closed'] as $value => $label)
                    <option value="{{ $value }}" @selected($conversation->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-lg border px-3 py-2 text-sm">Update status</button>
        </form>
        <form method="POST" action="{{ route('admin.support.destroy', $conversation) }}" onsubmit="return confirm('Permanently delete this conversation and its uploaded attachments?')">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete conversation</button>
        </form>
    </div>
</div>
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <div id="chatMessages" data-poll-url="{{ route('admin.support.conversation-poll', $conversation) }}" class="max-h-[60vh] space-y-3 overflow-y-auto p-2">
            @include('admin.support._messages', ['conversation' => $conversation])
        </div>
        @if($conversation->status !== 'closed')
            <form method="POST" action="{{ route('admin.support.reply', $conversation) }}" class="mt-3 flex flex-col gap-2 border-t pt-3 sm:flex-row">
                @csrf
                <textarea name="body" rows="2" required maxlength="3000" placeholder="Write a reply..." class="min-w-0 flex-1 rounded-lg border px-3 py-2"></textarea>
                <button class="self-end rounded-lg bg-espresso px-4 py-2 text-white">Send</button>
            </form>
        @endif
    </section>
    <aside class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">{{ $conversation->user?->role === 'staff' ? 'Staff profile' : 'Customer profile' }}</h2>
            <div class="mt-3 flex items-center gap-3">
                @if($conversation->user?->avatar)
                    <img src="{{ asset('storage/' . ltrim($conversation->user->avatar, '/')) }}" alt="Customer profile photo" class="h-16 w-16 rounded-full object-cover ring-2 ring-amber-100">
                @else
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-amber-100 text-2xl font-semibold text-espresso">{{ strtoupper(substr($conversation->user->name ?? $conversation->guest_name ?? 'C', 0, 1)) }}</div>
                @endif
                <div class="min-w-0">
                    <p class="break-words text-sm font-semibold">{{ $conversation->user->name ?? $conversation->guest_name ?? 'Guest customer' }}</p>
                <p class="text-sm text-espresso/60">{{ $conversation->user->email ?? $conversation->guest_contact ?? 'No contact supplied' }} · {{ ucfirst(str_replace('_', ' ', $conversation->status)) }}@if($conversation->user?->role) · {{ ucfirst($conversation->user->role) }}@endif</p>
                </div>
            </div>
            @if($conversation->user?->phone)<p class="mt-2 text-sm text-espresso/60">Phone: {{ $conversation->user->phone }}</p>@endif
        </section>
        @if($conversation->user_id && $conversation->user?->role === 'customer')
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Order / Refund</h2>
            @if($conversation->refundOrder)
                <p class="mt-2 text-sm">{{ $conversation->refundOrder->order_number }} · {{ $conversation->refundOrder->payment_status }} · ₱{{ number_format($conversation->refundOrder->total, 2) }}</p>
                @if($conversation->refundOrder->payment_status === 'paid')
                    <form method="POST" action="{{ route('admin.support.refund', $conversation) }}" class="mt-3" onsubmit="return confirm('Process the payment refund for this order?')">
                        @csrf<button class="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white">Process refund</button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-espresso/60">Only paid orders are eligible for this refund action.</p>
                @endif
            @endif
            <form method="POST" action="{{ route('admin.support.link-order', $conversation) }}" class="mt-3 space-y-2">
                @csrf<label class="block text-xs">Link a customer order</label>
                <select name="order_id" required class="w-full rounded-lg border px-2 py-2 text-sm">
                    <option value="">Select order</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}" @selected($conversation->refund_order_id === $order->id)>{{ $order->order_number }} · {{ $order->status }} · ₱{{ number_format($order->total, 2) }}</option>
                    @endforeach
                </select>
                <button class="w-full rounded-lg border px-3 py-2 text-sm">Link order</button>
            </form>
        </section>
        @endif
    </aside>
</div>
@endsection
@push('scripts')
<script>
(()=>{const box=document.getElementById('chatMessages');if(!box)return;const poll=async()=>{if(document.hidden)return;const nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100;try{const response=await fetch(box.dataset.pollUrl,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});if(response.ok){const data=await response.json();box.innerHTML=data.html;if(nearBottom)box.scrollTop=box.scrollHeight}}catch(_){}};box.scrollTop=box.scrollHeight;setInterval(poll,4000)})();
</script>
@endpush
