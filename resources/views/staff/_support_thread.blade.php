<div id="support-thread" data-poll-url="{{ route('staff.support.poll', $conversation) }}" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-cream px-4 py-3">
        <div>
            <strong class="text-sm text-espresso">Ticket #{{ $conversation->id }}</strong>
            <p class="text-xs text-espresso/60">{{ $conversation->created_at?->format('M j, Y g:i A') }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $conversation->status === 'closed' ? 'bg-stone-200 text-stone-700' : ($conversation->status === 'waiting_admin' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-800') }}">
            {{ str($conversation->status)->replace('_', ' ')->title() }}
        </span>
    </div>

    <div class="max-h-[55vh] min-h-64 space-y-3 overflow-y-auto rounded-2xl border border-espresso/10 bg-white p-4" data-thread-messages>
        @forelse($conversation->messages as $message)
            @php($mine = $message->sender_type === 'staff')
            <div class="flex items-end gap-2 {{ $mine ? 'justify-end' : 'justify-start' }}">
                @if(!$mine)
                    @if($message->sender?->avatar)
                        <img src="{{ asset('storage/' . ltrim($message->sender->avatar, '/')) }}" alt="Administrator profile" class="h-8 w-8 rounded-full object-cover">
                    @else
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-amber-100 text-xs font-bold text-espresso">{{ strtoupper(substr($message->sender?->name ?? 'A', 0, 1)) }}</span>
                    @endif
                @endif
                <article class="max-w-[88%] rounded-2xl {{ $mine ? 'rounded-br-md bg-espresso text-white' : 'rounded-bl-md bg-amber-50 text-espresso' }} px-4 py-3 shadow-sm">
                    <div class="mb-1 text-[11px] font-semibold opacity-75">{{ $mine ? 'You' : ($message->sender?->name ?? 'Administrator') }}</div>
                    <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $message->body }}</p>
                    <time class="mt-2 block text-right text-[10px] opacity-60">{{ $message->created_at?->format('M j, g:i A') }}</time>
                </article>
                @if($mine)
                    @if($message->sender?->avatar)
                        <img src="{{ asset('storage/' . ltrim($message->sender->avatar, '/')) }}" alt="Your profile" class="h-8 w-8 rounded-full object-cover">
                    @else
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-caramel text-xs font-bold text-white">{{ strtoupper(substr($message->sender?->name ?? 'S', 0, 1)) }}</span>
                    @endif
                @endif
            </div>
        @empty
            <p class="py-10 text-center text-sm text-espresso/55">Send a message to open a support ticket. The administrator's replies will appear here.</p>
        @endforelse
    </div>

    @if($conversation->status === 'closed')
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">This ticket is closed. Sending a new message will open a new ticket.</p>
    @endif

    <form method="POST" action="{{ route('staff.support.send') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        @csrf
        <label class="min-w-0 flex-1">
            <span class="mb-1 block text-sm font-semibold text-espresso">Message the administrator</span>
            <textarea name="body" rows="3" maxlength="3000" required placeholder="Describe the issue and what you need help with…" class="w-full resize-y rounded-xl border border-espresso/15 bg-white px-4 py-3 text-sm focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">{{ old('body') }}</textarea>
        </label>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-espresso px-5 py-3 font-semibold text-white shadow-sm hover:bg-[#624033] focus:outline-none focus:ring-2 focus:ring-caramel focus:ring-offset-2">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Send message
        </button>
    </form>
</div>
