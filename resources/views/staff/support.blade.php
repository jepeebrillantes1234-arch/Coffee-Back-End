@extends('layouts.staff')
@section('title', 'Help & Support')

@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-900">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.3 1.7c-1.1 1-1.9 1.3-1.9 2.8M12 17h.01"/></svg>
                Staff support
            </p>
            <h1 class="mb-1 text-2xl font-bold text-espresso">Help &amp; Support</h1>
                        Start a support ticket. The administrator's reply and your message history will appear here.
        </div>
        <a href="{{ route('staff.orders') }}" class="rounded-xl border border-espresso/15 bg-white px-4 py-2 text-sm font-semibold text-espresso hover:bg-cream">Back to Orders</a>
    </header>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
        <section class="min-w-0 rounded-2xl border border-espresso/10 bg-white p-4 shadow-sm sm:p-6">
            @if($selected)
                @include('staff._support_thread', ['conversation' => $selected])
            @else
                <form method="POST" action="{{ route('staff.support.send') }}" class="space-y-4">
                    @csrf
                    <div class="flex items-center gap-3 rounded-xl bg-cream p-4 text-sm text-espresso/70">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-amber-100 text-espresso">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v12H8l-4 3z"/><path d="M8 9h8M8 13h5"/></svg>
                        </span>
                        Start a support ticket. The administrator's reply and your message history will appear here.
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-espresso">Describe the issue</span>
                        <textarea name="body" rows="5" required maxlength="3000" placeholder="Include the order number or screen name if it relates to an issue…" class="w-full rounded-xl border border-espresso/15 bg-white px-4 py-3 text-sm focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">{{ old('body') }}</textarea>
                    </label>
                        Start a support ticket. The administrator's reply and your message history will appear here.
                </form>
            @endif
        </section>

        <aside class="rounded-2xl border border-espresso/10 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-bold text-espresso">Ticket history</h2>
                <span class="rounded-full bg-cream px-2.5 py-1 text-xs text-espresso/65">{{ $conversations->total() }}</span>
            </div>
            <div class="space-y-2">
                @forelse($conversations as $ticket)
                    @php($latestMessage = $ticket->messages->first())
                    <a href="{{ route('staff.support', ['conversation' => $ticket->id]) }}" class="block rounded-xl border px-3 py-3 no-underline transition hover:border-caramel {{ $selected?->id === $ticket->id ? 'border-caramel bg-amber-50/70' : 'border-espresso/10 bg-white' }}">
                        <span class="flex items-center justify-between gap-2">
                            <strong class="text-sm text-espresso">Ticket #{{ $ticket->id }}</strong>
                            <span class="text-[10px] text-espresso/55">{{ $ticket->last_message_at?->diffForHumans() }}</span>
                        </span>
                        <span class="mt-1 block text-xs text-espresso/65">{{ str($ticket->status)->replace('_', ' ')->title() }}</span>
                        <span class="mt-2 block line-clamp-2 text-xs text-espresso/75">{{ $latestMessage?->body ?? 'No messages yet' }}</span>
                    </a>
                @empty
                    <p class="rounded-xl bg-cream p-4 text-sm text-espresso/60">No support tickets yet.</p>
                @endforelse
            </div>
            @if($conversations->hasPages())
                <div class="mt-3">{{ $conversations->links() }}</div>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
@if($selected && $selected->status !== 'closed')
<script>
(() => {
    const thread = document.getElementById('support-thread');
    if (!thread) return;
    const poll = async () => {
        if (document.hidden) return;
        const messages = thread.querySelector('[data-thread-messages]');
        const shouldScroll = messages && messages.scrollHeight - messages.scrollTop - messages.clientHeight < 80;
        try {
            const response = await fetch(thread.dataset.pollUrl, { cache: 'no-store', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const result = await response.json();
            thread.outerHTML = result.html;
            if (shouldScroll) {
                const updated = document.querySelector('#support-thread [data-thread-messages]');
                if (updated) updated.scrollTop = updated.scrollHeight;
            }
            if (result.status === 'closed') window.location.reload();
        } catch (_) { /* Keep the current conversation visible while offline. */ }
    };
    const messages = thread.querySelector('[data-thread-messages]');
    if (messages) messages.scrollTop = messages.scrollHeight;
    window.setInterval(poll, 4000);
})();
</script>
@endif
@endpush
