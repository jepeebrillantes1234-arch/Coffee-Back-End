<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') | Coffee Shop</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#faf9f8] text-espresso font-sans">
@php
    // Mga icon (stroke) para sa sidebar
    $icons = [
        'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'box'    => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>',
        'tag'    => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'dollar' => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9a2.5 2.5 0 0 0-2.5-1.5c-1.4 0-2.5.8-2.5 2s1.1 1.7 2.5 2 2.5.8 2.5 2-1.1 2-2.5 2A2.5 2.5 0 0 1 9.5 15M12 6v1.5M12 16.5V18"/>',
        'users'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'badge'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M7 18c.8-2 2.6-3 5-3s4.2 1 5 3"/>',
        'bike'   => '<circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM12 17.5V14l-3-3 4-3 2 3h3"/>',
        'bag'    => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'truck'  => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'pin'    => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'card'   => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
        'star'   => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'chart'  => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'file'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'cup'    => '<path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><path d="M6 1v3M10 1v3M14 1v3"/>',
    ];

    // [label, url, active pattern, icon]
    $groups = [
        'Overview' => [
            ['Dashboard', route('admin.dashboard'), 'admin', 'grid'],
        ],
        'Catalog' => [
            ['Products',        route('admin.products.index'),   'admin/products*',   'box'],
            ['Categories',      route('admin.categories.index'), 'admin/categories*', 'folder'],
            ['Promos',          route('admin.promos.index'),     'admin/promos*',     'tag'],
            ['Discount & Fees', route('admin.settings.edit'),    'admin/settings*',   'dollar'],
        ],
        'People' => [
            ['Customers', route('admin.users.index', 'customer'), 'admin/users/customer*', 'users'],
            ['Staff',     route('admin.users.index', 'staff'),    'admin/users/staff*',    'badge'],
            ['Riders',    route('admin.users.index', 'rider'),    'admin/users/rider*',    'bike'],
        ],
        'Operations' => [
            ['Orders',        route('admin.orders.index'),          'admin/orders*',        'bag'],
            ['Deliveries',    route('admin.deliveries.index'),      'admin/deliveries*',    'truck'],
            ['GPS Monitor',   route('admin.gps.index'),             'admin/gps-monitor*',   'pin'],
            ['Payments',      route('admin.payments.index'),        'admin/payments*',      'card'],
            ['Reviews',       route('admin.reviews.index'),         'admin/reviews*',       'star'],
            ['Reports',       route('admin.reports.show', 'sales'), 'admin/reports*',       'chart'],
            ['Activity Logs', route('admin.activity.index'),        'admin/activity-logs*', 'file'],
        ],
    ];

    $svg = fn (string $key, string $cls = 'w-[18px] h-[18px]') =>
        '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$key] ?? '') . '</svg>';

    $user = auth()->user();
@endphp

<div class="flex min-h-screen">
    {{-- ================= SIDEBAR ================= --}}
    <aside class="w-72 shrink-0 bg-white border-r border-black/5 flex flex-col h-screen sticky top-0">
        <div class="px-5 py-5 flex items-center gap-3 border-b border-black/5">
            <div class="w-11 h-11 rounded-xl bg-espresso text-cream grid place-items-center">{!! $svg('cup', 'w-5 h-5') !!}</div>
            <div class="leading-tight">
                <p class="font-semibold text-sm">Coffee Shop</p>
                <p class="text-[11px] text-gray-400">Admin Panel</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-4">
            @foreach ($groups as $label => $items)
                <p class="px-3 pt-5 pb-2 text-[10px] font-semibold tracking-[0.18em] uppercase text-gray-400">{{ $label }}</p>
                <div class="space-y-1">
                    @foreach ($items as [$text, $url, $pattern, $icon])
                        @php $active = request()->is($pattern); @endphp
                        <a href="{{ $url }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm transition
                                  {{ $active ? 'bg-espresso text-white font-medium shadow-md' : 'text-gray-700 hover:bg-gray-100' }}">
                            <span class="w-8 h-8 rounded-xl grid place-items-center shrink-0 {{ $active ? 'bg-white/15' : 'bg-gray-100 text-gray-500' }}">{!! $svg($icon) !!}</span>
                            <span>{{ $text }}</span>
                            @if ($active) <span class="ml-auto w-1.5 h-1.5 rounded-full bg-caramel"></span> @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="p-3 border-t border-black/5 space-y-2">
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-2xl border border-black/5">
                <div class="w-9 h-9 rounded-full bg-cream text-espresso grid place-items-center text-sm font-semibold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="leading-tight min-w-0">
                    <p class="text-sm font-medium truncate">{{ $user->name }}</p>
                    <p class="text-[11px] text-gray-400">Administrator</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm text-gray-600 hover:bg-gray-100">
                    <span class="w-8 h-8 rounded-xl grid place-items-center bg-gray-100 text-gray-500">{!! $svg('logout') !!}</span> Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- ================= NILALAMAN ================= --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <header class="h-[68px] bg-white border-b border-black/5 flex items-center justify-between px-8">
            <div class="leading-tight">
                <p class="text-[10px] font-semibold tracking-[0.18em] uppercase text-gray-400">Admin Workspace</p>
                <p class="text-sm font-semibold">@yield('title', 'Dashboard')</p>
            </div>
            <span class="flex items-center gap-2 text-xs font-medium text-success bg-success/10 border border-success/20 rounded-full px-3 py-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-success"></span> System Active
            </span>
        </header>

        <main class="p-8 min-w-0">
            @if (session('success'))
                <div class="mb-4 rounded-xl bg-success/10 border border-success/30 text-success px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>