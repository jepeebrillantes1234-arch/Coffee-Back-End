@extends('errors.layout')
@section('title', 'Service interruption')
@section('code', '502')
@section('heading', 'A service interruption stopped this request.')
@section('description', 'Please wait a moment and try again. If you were placing an order or making a payment, check its status before submitting again.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    @if ($supportUrl)
        <a class="button button-secondary" href="{{ $supportUrl }}">Contact Support</a>
    @endif
@endsection
