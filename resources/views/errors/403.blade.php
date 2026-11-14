@extends('errors.layout')
@section('title', 'Access denied')
@section('code', '403')
@section('heading', 'You do not have access to this page.')
@section('description', 'Your account is not permitted to open this area. If you believe this is a mistake, sign in with an account that has the required access.')
@section('actions')
    @if ($role === 'admin')
        <a class="button" href="{{ route('admin.dashboard') }}">Return to Admin Dashboard</a>
    @elseif ($role === 'staff')
        <a class="button" href="{{ route('staff.home') }}">Return to Staff Dashboard</a>
    @else
        <a class="button" href="{{ route('login') }}">Go to Login</a>
    @endif
@endsection
