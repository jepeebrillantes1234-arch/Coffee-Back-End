@extends('layouts.admin')
@php $title = ($user->exists ? 'Edit ' : 'Bagong ') . ($role === 'rider' ? 'Rider' : 'Staff'); @endphp
@section('title', $title)
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $title }}</h1>
@php $in = 'w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel'; $p = $user->riderProfile; @endphp
<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store', $role) }}"
      class="bg-white rounded-2xl shadow-sm p-6 max-w-xl space-y-4">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    @foreach ([['name','Pangalan','text',true],['email','Email','email',true],['phone','Phone','text',false]] as [$f,$l,$t,$req])
        <div>
            <label class="block text-sm font-medium mb-1">{{ $l }}</label>
            <input type="{{ $t }}" name="{{ $f }}" value="{{ old($f, $user->$f) }}" @required($req) class="{{ $in }}">
            @error($f) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <div>
        <label class="block text-sm font-medium mb-1">Password {{ $user->exists ? '(iwanang blangko kung hindi babaguhin)' : '' }}</label>
        <input type="password" name="password" @required(!$user->exists) class="{{ $in }}">
        @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    @if ($role === 'rider')
        <div class="grid sm:grid-cols-3 gap-4">
            <div><label class="block text-sm font-medium mb-1">Sasakyan</label><input name="vehicle_type" value="{{ old('vehicle_type', $p->vehicle_type ?? 'Motorcycle') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm font-medium mb-1">Plate no.</label><input name="plate_number" value="{{ old('plate_number', $p->plate_number ?? '') }}" class="{{ $in }}"></div>
            <div><label class="block text-sm font-medium mb-1">License no.</label><input name="license_no" value="{{ old('license_no', $p->license_no ?? '') }}" class="{{ $in }}"></div>
        </div>
    @endif

    <div class="flex gap-3 pt-2">
        <button class="bg-espresso text-cream px-5 py-2 rounded-lg font-medium">I-save</button>
        <a href="{{ route('admin.users.index', $role) }}" class="px-5 py-2 rounded-lg border border-espresso/20">Kanselahin</a>
    </div>
</form>
@endsection
