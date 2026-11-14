@extends('errors.layout')
@section('title', 'Page not found')
@section('code', '404')
@section('heading', 'We could not find that page.')
@section('description', 'The page may have moved, or the address may be incorrect. Return to KJM Coffee Shop or go back to the previous page.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Go to Homepage</a>
    <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
@endsection
