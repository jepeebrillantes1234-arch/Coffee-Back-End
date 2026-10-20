@extends('layouts.admin')
@section('title', 'My Profile')
@section('content')
<div class="max-w-3xl">
    <h1 class="text-2xl font-semibold mb-2">My Profile</h1>
    <p class="text-sm text-espresso/60 mb-6">Manage your administrator profile, profile photo, and password.</p>
    <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <section class="rounded-2xl bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center gap-5">
                <div class="h-24 w-24 overflow-hidden rounded-full bg-[#f2e3d5] grid place-items-center text-3xl font-bold text-[#573429]">
                    @if($user->avatar)
                        <img src="{{ asset('storage/'.$user->avatar) }}" alt="Administrator profile photo" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                <div><label for="avatar" class="mb-1 block font-semibold">Profile photo</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="block max-w-full text-sm"><p class="mt-1 text-xs text-espresso/60">JPG, PNG, or WebP. Maximum 5 MB.</p></div>
            </div>
            <div><label class="mb-1 block text-sm">Full name</label><input name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm">Email address</label><input name="email" type="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
                <div><label class="mb-1 block text-sm">Phone number</label><input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
            </div>
        </section>
        <section id="security" class="rounded-2xl bg-white p-6 shadow-sm space-y-4">
            <h2 class="font-semibold">Security</h2>
            <p class="text-sm text-espresso/60">Leave password fields empty if you do not want to change it.</p>
            <div><label class="mb-1 block text-sm">Current password</label><input name="current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="mb-1 block text-sm">New password</label><input name="password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
                <div><label class="mb-1 block text-sm">Confirm new password</label><input name="password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border border-espresso/20 px-3 py-2"></div>
            </div>
        </section>
        <section id="about" class="rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="font-semibold">About KJM Coffee Shop</h2>
            <p class="mt-2 text-sm text-espresso/60">Administrator account for managing the KJM Coffee Shop workspace.</p>
        </section>        @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <button class="rounded-lg bg-espresso px-6 py-3 font-semibold text-white">Save Profile</button>
    </form>
</div>
@endsection
