@extends('errors.layout')
@section('title', 'Page expired')
@section('code', '419')
@section('heading', 'This page has expired.')
@section('description', 'For your security, this form or page is no longer valid. Sign in again if needed, then reopen the page and review your information before submitting.')
@section('note', 'Your previous form was not automatically submitted again. Do not use browser refresh to repeat a payment or order.')
@section('actions')
    <a class="button" href="{{ route('login') }}">Log In Again</a>
    <a class="button button-secondary" href="{{ $homeUrl }}">Go to a Safe Page</a>
@endsection
