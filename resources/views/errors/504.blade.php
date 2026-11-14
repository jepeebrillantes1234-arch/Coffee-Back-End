@extends('errors.layout')
@section('title', 'Gateway timed out')
@section('code', '504')
@section('heading', 'The service did not respond in time.')
@section('description', 'Wait a moment before trying again. Check your order or payment status first to avoid submitting the same request twice.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    @if ($supportUrl)
        <a class="button button-secondary" href="{{ $supportUrl }}">Contact Support</a>
    @endif
@endsection
