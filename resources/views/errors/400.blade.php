@extends('errors.layout')
@section('title', 'Bad request')
@section('code', '400')
@section('heading', 'We could not understand that request.')
@section('description', 'Check the information you entered and try again. If you were submitting an order or payment, check its status before sending it again.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
@endsection
