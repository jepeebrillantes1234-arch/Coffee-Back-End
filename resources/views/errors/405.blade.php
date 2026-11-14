@extends('errors.layout')
@section('title', 'Action not supported')
@section('code', '405')
@section('heading', 'That action is not available here.')
@section('description', 'The page does not support the request that was made. Return to the previous page and use one of its available actions.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
@endsection
