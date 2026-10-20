@foreach($conversation->messages as $message)
    @php($mine = $message->sender_type === 'admin')
    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[85%] rounded-2xl {{ $mine ? 'bg-espresso text-white' : ($message->sender_type === 'assistant' ? 'bg-amber-50 text-espresso' : 'bg-gray-100 text-espresso') }} p-3">
            <div class="mb-1 text-[11px] font-semibold">{{ $mine ? 'You · Administrator' : ($message->sender_type === 'assistant' ? 'KJM Assistant' : 'Customer') }}</div>
            @if($message->body)<p class="whitespace-pre-wrap text-sm">{{ $message->body }}</p>@endif
            @if($message->attachment_path)
                @php($src = route('admin.support.attachment', [$conversation, $message]))
                @if(str_starts_with($message->attachment_mime ?? '', 'image/'))<a href="{{ $src }}" target="_blank"><img src="{{ $src }}" alt="Customer attachment" class="mt-2 max-h-72 max-w-full rounded-xl"></a>
                @else<video controls preload="metadata" class="mt-2 max-h-72 max-w-full rounded-xl"><source src="{{ $src }}" type="{{ $message->attachment_mime }}"></video>@endif
                <a href="{{ $src }}" target="_blank" class="mt-2 block text-xs underline">{{ $message->attachment_name }}</a>
            @endif
            <div class="mt-1 text-right text-[10px] opacity-60">{{ $message->created_at->format('M j, g:i A') }}</div>
        </div>
    </div>
@endforeach