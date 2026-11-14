@extends('errors.layout')
@section('title', 'Sign in required')
@section('code', '401')
@section('heading', 'Please sign in to continue.')
@section('description', 'Your session may have ended. Sign in again, then return to the page you were using.')
@section('actions')
    <a class="button" href="{{ route('login') }}">Go to Login</a>
@endsection
