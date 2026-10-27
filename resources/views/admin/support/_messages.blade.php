@foreach($conversation->messages as $message)
    @php($mine = $message->sender_type === 'admin')
    <div class="flex items-end gap-2 {{ $mine ? 'justify-end' : 'justify-start' }}">
        @if(!$mine)
            @if($message->sender_type === 'assistant')
                <div class="mb-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-espresso" aria-label="KJM Assistant"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8h11v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V8Z"/><path d="M16 10h2a2 2 0 0 1 0 4h-2M7 21h10M9 4c0 1 1 1 1 2m3-2c0 1 1 1 1 2"/></svg></div>
            @elseif($message->sender?->avatar ?? $conversation->user?->avatar)
                <img src="{{ asset('storage/' . ltrim($message->sender?->avatar ?? $conversation->user->avatar, '/')) }}" alt="{{ $message->sender?->name ?? $conversation->user?->name ?? 'Sender' }} profile" class="mb-1 h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-black/10">
            @else
                <div class="mb-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $message->sender_type === 'staff' ? 'bg-violet-100' : 'bg-gray-200' }} text-xs font-semibold text-espresso">{{ strtoupper(substr($message->sender?->name ?? $conversation->user?->name ?? $conversation->guest_name ?? 'C', 0, 1)) }}</div>
            @endif
        @endif
        <div class="max-w-[85%] rounded-2xl {{ $mine ? 'bg-espresso text-white' : ($message->sender_type === 'assistant' ? 'bg-amber-50 text-espresso' : 'bg-gray-100 text-espresso') }} p-3">
            <div class="mb-1 text-[11px] font-semibold">{{ $mine ? 'You · Administrator' : ($message->sender_type === 'assistant' ? 'KJM Assistant' : (($message->sender_type === 'staff' || $message->sender?->role === 'staff') ? 'Staff · ' . ($message->sender?->name ?? $conversation->user?->name ?? 'Team member') : ($message->sender?->name ?? $conversation->user?->name ?? $conversation->guest_name ?? 'Customer'))) }}</div>
            @if($message->body)<p class="whitespace-pre-wrap text-sm">{{ $message->body }}</p>@endif
            @if($message->attachment_path)
                @php($src = route('admin.support.attachment', [$conversation, $message]))
                @if(str_starts_with($message->attachment_mime ?? '', 'image/'))<a href="{{ $src }}" target="_blank"><img src="{{ $src }}" alt="Customer attachment" class="mt-2 max-h-72 max-w-full rounded-xl"></a>
                @else<video controls preload="metadata" class="mt-2 max-h-72 max-w-full rounded-xl"><source src="{{ $src }}" type="{{ $message->attachment_mime }}"></video>@endif
                <a href="{{ $src }}" target="_blank" class="mt-2 block text-xs underline">{{ $message->attachment_name }}</a>
            @endif
            <div class="mt-1 text-right text-[10px] opacity-60">{{ $message->created_at->format('M j, g:i A') }}</div>
        </div>
        @if($mine)
            @if($message->sender?->avatar)
                <img src="{{ asset('storage/' . ltrim($message->sender->avatar, '/')) }}" alt="Administrator profile" class="mb-1 h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-black/10">
            @else
                <div class="mb-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-espresso text-xs font-semibold text-white">{{ strtoupper(substr($message->sender?->name ?? 'A', 0, 1)) }}</div>
            @endif
        @endif
    </div>
@endforeach
