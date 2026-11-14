@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', '500')
@section('heading', 'We are sorry - something went wrong.')
@section('description', 'The page could not be completed right now. No technical details are shown here. Please try again later or contact support if the problem continues.')
@section('actions')
    <a class="button" href="{{ $homeUrl }}">Return to KJM Coffee Shop</a>
    @if ($supportUrl)
        <a class="button button-secondary" href="{{ $supportUrl }}">Contact Support</a>
    @endif
@endsection
