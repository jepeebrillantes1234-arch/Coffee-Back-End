<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Staff') | Coffee Shop</title>
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream text-espresso font-sans min-h-screen">
@php
    $pending = \App\Models\Order::where('status', 'pending')->count();
    $tabs = [
        ['staff.orders',       'Orders',       'staff/orders*'],
        ['staff.pos',          'Walk-in POS',  'staff/pos*'],
        ['staff.menu',         'Menu / Sold out', 'staff/menu*'],
        ['staff.transactions', 'Transactions', 'staff/transactions*'],
    ];
@endphp
<header class="bg-espresso text-cream">
    <div class="px-5 py-3 flex flex-wrap items-center gap-4">
        <span class="font-semibold text-lg">☕ Staff Panel</span>
        <nav class="flex gap-1">
            @foreach ($tabs as [$route, $label, $pattern])
                <a href="{{ route($route) }}" class="px-4 py-2 rounded-lg text-sm hover:bg-white/10 {{ request()->is($pattern) ? 'bg-white/15 font-medium' : '' }}">
                    {{ $label }}
                    @if ($route === 'staff.orders')
                        <span id="nav-pending" class="ml-1 px-2 py-0.5 rounded-full text-xs bg-caramel text-white {{ $pending ? '' : 'hidden' }}">{{ $pending }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-3 text-sm">
            <span class="text-cream/70">{{ auth()->user()->name }}</span>
            @if (auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg bg-white/10">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="px-3 py-1.5 rounded-lg hover:bg-white/10">Logout</button></form>
        </div>
    </div>
</header>

<main class="p-5">
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-success/10 border border-success/30 text-success px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
