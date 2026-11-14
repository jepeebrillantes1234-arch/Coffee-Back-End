@extends('errors.layout')
@section('title', 'Please wait before retrying')
@section('code', '429')
@section('heading', 'You are making requests too quickly.')
@section('description')
    @php
        $retryAfter = $exception?->getHeaders()['Retry-After'] ?? null;
        $retrySeconds = is_numeric($retryAfter) ? max(0, (int) $retryAfter) : null;
        $retryAt = null;
        if ($retrySeconds === null && is_string($retryAfter)) {
            try {
                $candidate = \Illuminate\Support\Carbon::parse($retryAfter);
                $retryAt = $candidate->isFuture() ? $candidate->format('g:i:s A T') : null;
            } catch (\Throwable) {
                $retryAt = null;
            }
        }
    @endphp
    A short rate limit protects the service. Please wait
    @if ($retrySeconds !== null && $retrySeconds > 0)
        {{ $retrySeconds }} {{ \Illuminate\Support\Str::plural('second', $retrySeconds) }}
    @elseif ($retryAt)
        until {{ $retryAt }}
    @else
        a moment
    @endif
    before trying again.
@endsection
@section('note', 'Repeatedly submitting a form will not make it process faster. Wait for the retry window, then continue from the page.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    @if ($supportUrl)
        <a class="button button-secondary" href="{{ $supportUrl }}">Contact Support</a>
    @endif
@endsection
