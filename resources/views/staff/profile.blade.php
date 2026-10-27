@extends('layouts.staff')
@section('title', 'Staff Profile')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-caramel">Account</p>
            <h1 class="mt-1 text-2xl font-bold text-espresso">Profile &amp; Security</h1>
            <p class="mt-1 text-sm text-espresso/65">Update your contact details and manage your password.</p>
        </div>
        <a href="{{ route('staff.orders') }}" class="rounded-xl border border-espresso/15 bg-white px-4 py-2 text-sm font-semibold text-espresso hover:bg-cream">Back to Orders</a>
    </header>

    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('success') }}</div>
    @endif

    <section class="rounded-2xl border border-espresso/10 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold text-espresso">Profile information</h2>
        <p class="mt-1 text-sm text-espresso/60">These details are used to identify your staff account.</p>
        <div class="mt-5 flex flex-wrap items-center gap-4">
            <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-full bg-amber-100 text-2xl font-bold text-espresso">
                @if($user->avatar)<img src="{{ asset('storage/' . ltrim($user->avatar, '/')) }}" alt="{{ $user->name }} profile photo" class="h-full w-full object-cover">@else{{ strtoupper(substr($user->name, 0, 1)) }}@endif
            </div>
            <form method="POST" action="{{ route('staff.profile.avatar') }}" enctype="multipart/form-data" class="flex min-w-0 flex-1 flex-wrap items-end gap-3">
                @csrf
                <label class="min-w-0 flex-1 text-sm font-medium text-espresso">Change profile photo
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full rounded-lg border border-espresso/15 p-2 text-sm">
                </label>
                <button type="submit" class="rounded-xl bg-espresso px-4 py-2.5 text-sm font-semibold text-white">Upload photo</button>
            </form>
        </div>
        @error('avatar')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('staff.profile.update') }}" class="mt-6 space-y-4">
            @csrf @method('PUT')
            <label class="block text-sm font-semibold text-espresso">Full name
                <input name="name" value="{{ old('name', $user->name) }}" maxlength="120" required autocomplete="name" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
                @error('name')<span class="mt-1 block font-normal text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-espresso">Email address
                <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="190" required autocomplete="email" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
                @error('email')<span class="mt-1 block font-normal text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-espresso">Phone number
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
                @error('phone')<span class="mt-1 block font-normal text-red-700">{{ $message }}</span>@enderror
            </label>
            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-espresso px-5 py-2.5 font-semibold text-white">Save profile</button></div>
        </form>
    </section>

    <section id="change-password" class="scroll-mt-5 rounded-2xl border border-espresso/10 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold text-espresso">Change password</h2>
        <p class="mt-1 text-sm text-espresso/60">Confirm with your current password. Your password is never shown in admin activity logs.</p>
        <form method="POST" action="{{ route('staff.profile.password') }}" class="mt-5 space-y-4">
            @csrf @method('PUT')
            <label class="block text-sm font-semibold text-espresso">Current password
                <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
                @error('current_password')<span class="mt-1 block font-normal text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-espresso">New password
                <input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
                <span class="mt-1 block font-normal text-espresso/55">At least 8 characters.</span>
                @error('password')<span class="mt-1 block font-normal text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-espresso">Confirm new password
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-espresso/15 px-3 py-2.5 font-normal focus:border-caramel focus:outline-none focus:ring-2 focus:ring-caramel/20">
            </label>
            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-espresso px-5 py-2.5 font-semibold text-white">Update password</button></div>
        </form>
    </section>
</div>
@endsection
