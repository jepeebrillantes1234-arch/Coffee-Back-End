@extends('errors.layout')
@section('title', 'Temporarily unavailable')
@section('code', '503')
@section('heading', 'KJM Coffee Shop is temporarily unavailable.')
@section('description', 'The service may be undergoing maintenance or experiencing a temporary interruption. Please wait a little and try again.')
@section('note', 'Check your order history before attempting to submit an order again.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    @if ($supportUrl)
        <a class="button button-secondary" href="{{ $supportUrl }}">Contact Support</a>
    @endif
@endsection
