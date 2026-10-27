<div class="grid gap-3">
    @forelse($conversations as $conversation)
        @php($last = $conversation->messages->first())
        <article class="rounded-2xl border border-espresso/10 bg-white p-4 shadow-sm hover:border-caramel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('admin.support.show', $conversation) }}" class="min-w-0 flex-1 text-inherit no-underline">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div><strong>{{ $conversation->user->name ?? $conversation->guest_name ?? 'Guest customer' }}</strong>@if($conversation->user)<span class="ml-2 rounded-full {{ $conversation->user->role === 'staff' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }} px-2 py-1 text-xs font-semibold">{{ ucfirst($conversation->user->role) }}</span>@endif<span class="ml-2 rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ str_replace('_', ' ', ucfirst($conversation->status)) }}</span></div>
                        <span class="text-xs text-espresso/50">{{ optional($conversation->last_message_at)->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-xs text-espresso/60">{{ $conversation->user->email ?? $conversation->guest_contact ?? 'No customer contact' }}</p>
                    <p class="mt-2 line-clamp-2 text-sm">{{ $last?->body ?: ($last?->attachment_name ? 'Attachment: '.$last->attachment_name : 'No messages yet') }}</p>
                </a>
                <form method="POST" action="{{ route('admin.support.destroy', $conversation) }}" onsubmit="return confirm('Permanently delete this conversation and its uploaded attachments?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                </form>
            </div>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-8 text-center text-espresso/60">No support conversations yet.</p>
    @endforelse
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
