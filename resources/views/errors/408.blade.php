@extends('errors.layout')
@section('title', 'Request timed out')
@section('code', '408')
@section('heading', 'The request took too long.')
@section('description', 'Check your connection and try again. For an order or payment, check your order history first so you do not submit it twice.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
@endsection
