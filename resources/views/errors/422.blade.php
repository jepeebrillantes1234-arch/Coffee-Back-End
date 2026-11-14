@extends('errors.layout')
@section('title', 'Check your information')
@section('code', '422')
@section('heading', 'Some information needs your attention.')
@section('description', 'Review the fields on the previous page and correct anything missing or invalid before continuing.')
@section('actions')
    <button class="button" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Return to Form</button>
    <a class="button button-secondary" href="{{ $homeUrl }}">Go to Homepage</a>
@endsection
