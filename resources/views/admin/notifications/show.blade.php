@extends('layouts.admin')
@section('title', 'Notification Details')
@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm p-6 md:p-8">
    <a class="text-sm text-espresso/70 underline" href="{{ route('admin.notifications.index') }}">&larr; Notification History</a>
    <div class="flex items-start justify-between gap-4 mt-5"><h1 class="text-2xl font-semibold">{{ $record->title }}</h1><span class="rounded-full px-2 py-1 text-xs bg-espresso/10 text-espresso/70">{{ $record->is_read ? 'Read' : 'Unread' }}</span></div>
    <p class="mt-4 whitespace-pre-wrap">{{ $record->message }}</p>
    <dl class="grid sm:grid-cols-2 gap-4 mt-6 text-sm">
        <div><dt class="text-espresso/50">Received</dt><dd>{{ \Illuminate\Support\Carbon::parse($record->created_at)->format('M d, Y h:i A') }}</dd></div>
        @if ($record->is_read)<div><dt class="text-espresso/50">Read</dt><dd>{{ \Illuminate\Support\Carbon::parse($record->updated_at)->format('M d, Y h:i A') }}</dd></div>@endif
        <div><dt class="text-espresso/50">Type</dt><dd>{{ $record->type ?: 'General' }}</dd></div>
        @foreach (($record->data ?? []) as $key => $value)
            @if (is_scalar($value) && ! in_array($key, ['fcm_delivery_status','fcm_last_attempt_at','fcm_sent_at'], true))
                <div><dt class="text-espresso/50">{{ \Illuminate\Support\Str::headline($key) }}</dt><dd>{{ $value }}</dd></div>
            @endif
        @endforeach
    </dl>
    @if ($relatedUrl)<a class="inline-flex mt-7 rounded-lg bg-espresso px-4 py-2 text-cream" href="{{ $relatedUrl }}">Open related record</a>@endif
</div>
@endsection
