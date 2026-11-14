@extends('errors.layout')
@section('title', 'Request conflict')
@section('code', '409')
@section('heading', 'This information has changed.')
@section('description', 'Refresh the page to see the latest status, then review your action before trying again.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
@endsection
