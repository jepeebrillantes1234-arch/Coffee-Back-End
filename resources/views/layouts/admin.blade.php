<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') | KJM Coffee Shop</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--coffee:#3b2118;--coffee-2:#542e20;--caramel:#b86a2c;--cream:#fbf7f1;--ink:#2c211d;--muted:#7d7773;--line:#eee8e2;--surface:#fff;--sidebar-width:270px}
        *{box-sizing:border-box} body{margin:0;background:var(--cream);color:var(--ink);font-family:'DM Sans',sans-serif}
        .admin-shell{min-height:100vh;display:flex}.admin-sidebar{width:var(--sidebar-width);height:100vh;position:sticky;top:0;flex:none;background:linear-gradient(165deg,#321b13,#48271b 70%,#2b1711);color:#fff;display:flex;flex-direction:column;transition:width .22s,transform .22s;z-index:40;overflow:hidden}
        .brand{height:96px;padding:17px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #ffffff1c;white-space:nowrap}.brand-mark{width:48px;height:48px;border-radius:50%;background:#fff9ef;display:grid;place-items:center;overflow:hidden;flex:none}.brand-mark img{width:43px;height:43px;object-fit:contain}.brand-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;line-height:1.15}.brand-tagline{font-size:10px;color:#d7c3b2;margin-top:4px}
        .admin-nav{flex:1;overflow:auto;padding:10px 14px 18px}.nav-group{margin-top:10px}.nav-heading{padding:10px 8px 7px;color:#bda99b;font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:700;white-space:nowrap}.nav-link{display:flex;align-items:center;gap:12px;min-height:41px;padding:7px 10px;margin:3px 0;border-radius:11px;color:#f7eee7;text-decoration:none;font-size:13px;font-weight:500;white-space:nowrap;transition:.16s}.nav-link:hover{background:#ffffff15}.nav-link.active{background:linear-gradient(90deg,#8f4b22,#a95d2c);box-shadow:0 5px 14px #1c100c55;color:#fff}.nav-icon{width:29px;height:29px;display:grid;place-items:center;flex:none;color:#f5e6d7}.nav-icon svg{width:19px;height:19px}.nav-label{overflow:hidden;text-overflow:ellipsis}.nav-active-dot{margin-left:auto;width:6px;height:6px;border-radius:50%;background:#ffd18a}
        .admin-content{min-width:0;flex:1}.topbar{height:68px;display:flex;align-items:center;gap:17px;padding:0 28px;background:#fffdfb;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}.icon-button{border:0;background:transparent;color:var(--coffee);height:38px;width:38px;border-radius:11px;display:grid;place-items:center;cursor:pointer}.icon-button:hover{background:#f4eee8}.icon-button svg{width:20px;height:20px}.search-wrap{height:38px;width:min(390px,42vw);display:flex;align-items:center;gap:10px;padding:0 13px;border-radius:13px;background:#f5f0eb;color:#8b827b}.search-wrap svg{width:17px;height:17px;flex:none}.search-wrap input{width:100%;border:0;outline:0;background:transparent;font:inherit;font-size:12px;color:var(--ink)}.topbar-spacer{flex:1}.topbar-leaves{width:160px;height:48px;opacity:.5;position:relative;overflow:hidden}.topbar-leaves:before,.topbar-leaves:after{content:'';position:absolute;width:42px;height:21px;background:#b7784e;border-radius:100% 0 100% 0;transform:rotate(-32deg);box-shadow:35px 13px 0 #d4a785,68px -4px 0 #9e6242,99px 12px 0 #c58b65}.topbar-leaves:before{left:8px;top:11px}.topbar-leaves:after{left:37px;top:-3px;transform:rotate(145deg)}.admin-account{display:flex;align-items:center;gap:10px;min-width:150px}.account-avatar{width:36px;height:36px;border-radius:50%;background:#f2e3d5;color:var(--coffee);display:grid;place-items:center;overflow:hidden;flex:none}.account-avatar img{width:100%;height:100%;object-fit:cover}.account-name{font-size:12px;font-weight:700}.account-role{font-size:10px;color:var(--muted);margin-top:2px}.account-chevron{margin-left:5px;color:#88776b}
        .workspace{padding:22px 27px 32px;max-width:1800px;margin:auto}.workspace-heading{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:18px}.eyebrow{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:#918983;font-weight:700}.breadcrumb{display:flex;gap:8px;align-items:center;margin-top:5px;color:#8e8177;font-size:12px}.breadcrumb strong{color:var(--coffee)}.system-status{display:inline-flex;gap:8px;align-items:center;padding:8px 13px;background:#e8f8e9;border:1px solid #c9edca;border-radius:99px;color:#24753c;font-size:11px;font-weight:700}.system-status i{width:7px;height:7px;background:#3ba956;border-radius:50%}
        .flash{margin:0 0 14px;padding:12px 15px;border-radius:12px;font-size:13px}.flash-success{background:#eaf7ed;color:#237541;border:1px solid #cbe8d2}.flash-error{background:#fff0ee;color:#ae3328;border:1px solid #f3d0cb}
        .admin-shell{min-height:100vh;display:flex}.admin-sidebar{width:270px;height:100vh;position:sticky;top:0;flex:none;background:linear-gradient(165deg,#321b13,#48271b 70%,#2b1711);color:#fff;display:flex;flex-direction:column;transition:width .22s,transform .22s;z-index:40;overflow:hidden}.admin-nav{flex:1;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;padding:10px 14px 18px;scrollbar-width:thin;scrollbar-color:#a97858 transparent}.admin-content{min-width:0;flex:1}.topbar .search-wrap{margin-left:4px}.topbar-logout{height:36px;padding:0 9px;border:0;border-radius:9px;background:transparent;color:#72584a;display:flex;align-items:center;gap:6px;font:inherit;font-size:11px;font-weight:600;cursor:pointer}.topbar-logout:hover{background:#f4eee8}.topbar-logout svg{width:17px;height:17px}
        .admin-shell.is-collapsed .admin-sidebar{width:82px}.admin-shell.is-collapsed .brand{padding-left:17px}.admin-shell.is-collapsed .brand-copy,.admin-shell.is-collapsed .nav-heading,.admin-shell.is-collapsed .nav-label,.admin-shell.is-collapsed .nav-active-dot,.admin-shell.is-collapsed .profile-copy,.admin-shell.is-collapsed .logout-label{display:none}.admin-shell.is-collapsed .admin-nav{padding-left:13px;padding-right:13px}.admin-shell.is-collapsed .nav-link{justify-content:center;padding:6px}.admin-shell.is-collapsed .profile-mini{justify-content:center;padding-left:0;padding-right:0}.admin-shell.is-collapsed .logout-button{justify-content:center}.mobile-backdrop{display:none}
        @media(max-width:850px){.admin-sidebar{position:fixed;left:0;top:0;transform:translateX(-100%);width:270px!important}.admin-shell.mobile-open .admin-sidebar{transform:translateX(0)}.mobile-backdrop{position:fixed;inset:0;background:#1e130e88;z-index:30}.admin-shell.mobile-open .mobile-backdrop{display:block}.admin-shell.is-collapsed .brand-copy,.admin-shell.is-collapsed .nav-heading,.admin-shell.is-collapsed .nav-label,.admin-shell.is-collapsed .nav-active-dot,.admin-shell.is-collapsed .profile-copy,.admin-shell.is-collapsed .logout-label{display:block}.admin-shell.is-collapsed .nav-link{justify-content:flex-start;padding:7px 10px}.admin-shell.is-collapsed .profile-mini{justify-content:flex-start}.workspace{padding:20px 16px 28px}.topbar{padding:0 14px;gap:8px}.topbar-leaves{display:none}.admin-account{min-width:unset}.account-copy,.account-chevron{display:none}}
        @media(max-width:520px){.workspace{padding-left:12px;padding-right:12px}.topbar-logout .logout-text{display:none}.topbar-logout{padding:0 5px}.admin-profile-panel{top:64px;right:9px;width:210px}}
        .account-trigger{border:0;background:transparent;text-align:left;cursor:pointer;padding:5px 8px;border-radius:12px}.account-trigger:hover{background:#f5eee8}
        .admin-profile-shade{position:fixed;inset:0;background:transparent;z-index:60;opacity:0;visibility:hidden;transition:opacity .15s}.admin-profile-panel{position:fixed;top:74px;right:24px;width:210px;padding:8px;background:#fffdfb;color:#30231d;z-index:61;opacity:0;visibility:hidden;transform:translateY(-6px) scale(.98);transform-origin:top right;transition:opacity .16s,transform .16s,visibility .16s;overflow:hidden;border:1px solid #eee7e1;border-radius:12px;box-shadow:0 12px 32px #29190f24}.admin-profile-open .admin-profile-shade{opacity:1;visibility:visible}.admin-profile-open .admin-profile-panel{opacity:1;visibility:visible;transform:translateY(0) scale(1)}
        .admin-panel-head{display:flex;align-items:center;gap:10px;padding:8px 7px 11px;margin-bottom:4px;border-bottom:1px solid #eee8e2}.admin-panel-head-copy{min-width:0;display:flex;flex-direction:column}.admin-panel-head-copy strong{font-size:12px}.admin-panel-head-copy small{font-size:10px;color:#7d7773}.admin-panel-avatar{width:38px;height:38px;border-radius:50%;background:#f0e1d4;color:#573429;display:grid;place-items:center;font-size:15px;font-weight:700;overflow:hidden;flex:none}.admin-panel-avatar img{width:100%;height:100%;object-fit:cover}.admin-menu-link{display:flex;align-items:center;gap:9px;min-height:34px;padding:6px 7px;border-radius:8px;color:#302923;text-decoration:none;font-size:11px;font-weight:500}.admin-menu-link:hover{background:#f4eee8;color:#5b3020}.admin-menu-link svg{width:15px;height:15px;stroke:#7b7771;flex:none}.admin-menu-separator{height:1px;background:#eee8e2;margin:5px 0}.admin-menu-logout{width:100%;border:0;background:transparent;text-align:left;cursor:pointer;font:inherit}.admin-panel-close{border:0;background:#f3efeb;border-radius:10px;width:36px;height:36px;font-size:22px;cursor:pointer}.admin-panel-user{text-align:center;padding:23px 8px}.admin-panel-avatar{width:38px;height:38px;border-radius:50%;margin:0;background:#f0e1d4;color:#573429;display:grid;place-items:center;font-size:25px;font-weight:700;overflow:hidden}.admin-panel-avatar img{width:100%;height:100%;object-fit:cover}.admin-panel-link{display:flex;align-items:center;justify-content:space-between;padding:12px 4px;color:inherit;text-decoration:none;border-bottom:1px solid #eee9e5;font-size:13px}.admin-panel-link:last-child{border-bottom:0}.admin-panel-link:hover{color:#9a542b}.admin-panel-card{margin:10px 0;padding:15px;border:1px solid #eee9e5;border-radius:15px;background:#fff}.admin-panel-label{font-size:11px;color:#77808b;margin:9px 0 4px}.admin-panel-value{font-size:13px;font-weight:600;overflow-wrap:anywhere}.admin-theme-row{display:flex;gap:8px}.admin-theme-row button{flex:1;padding:9px;border:1px solid #e8e3de;border-radius:10px;background:#fff;color:#342720;cursor:pointer}.admin-theme-row button.active{background:#542e20;border-color:#542e20;color:#fff}
        .admin-profile-panel .admin-panel-avatar{width:38px;height:38px;margin:0;border-radius:50%;flex:none}body[data-admin-theme=dark]{--cream:#171412;--ink:#eee6df;--muted:#b9aaa0;--line:#40352f;--surface:#241e1a;--coffee:#f1e6dc;--coffee-2:#dfc2aa;--caramel:#e2a06a;color-scheme:dark}body[data-admin-theme=dark] .admin-content,body[data-admin-theme=dark] .workspace{background:#171412;color:#eee6df}body[data-admin-theme=dark] .topbar,body[data-admin-theme=dark] .admin-profile-panel,body[data-admin-theme=dark] .admin-panel-card,body[data-admin-theme=dark] .workspace [class~="bg-white"]{background:#241e1a!important;color:#eee6df}body[data-admin-theme=dark] .workspace [class~="bg-cream"]{background:#1d1916!important}body[data-admin-theme=dark] .workspace :is([class*="text-espresso"],[class*="text-gray-"],[class*="text-black"]),body[data-admin-theme=dark] .admin-profile-panel :is(.text-gray-700,.text-amber-900){color:#e8dfd7!important}body[data-admin-theme=dark] .admin-panel-label{color:#b7aaa0}body[data-admin-theme=dark] .admin-account,body[data-admin-theme=dark] .account-name,body[data-admin-theme=dark] .account-chevron{color:#eee6df}body[data-admin-theme=dark] .search-wrap{background:#332a25;color:#d6c9be}body[data-admin-theme=dark] .search-wrap input{color:#eee6df}body[data-admin-theme=dark] .workspace input:not([type=hidden]),body[data-admin-theme=dark] .workspace select,body[data-admin-theme=dark] .workspace textarea{background:#302823!important;color:#eee6df!important;border-color:#51443b!important}body[data-admin-theme=dark] .workspace table th,body[data-admin-theme=dark] .workspace table td,body[data-admin-theme=dark] .workspace hr{border-color:#463b35!important}body[data-admin-theme=dark] .workspace thead{background:#302721;color:#e8dfd7}body[data-admin-theme=dark] .workspace .breadcrumb strong,body[data-admin-theme=dark] .workspace .eyebrow{color:#d4c6ba}body[data-admin-theme=dark] .workspace [class*="text-caramel"]{color:#e1a876!important}body[data-admin-theme=dark] .account-trigger:hover{background:#382c25}body[data-admin-theme=dark] .admin-theme-row button{background:#302823;color:#eee6df;border-color:#51443b}body[data-admin-theme=dark] .admin-theme-row button.active{background:#8a4d2b;color:#fff}
        body[data-admin-theme=dark] .workspace :is(h1,h2,h3,h4,label,legend,dt,dd,th,td,small,p:not([class*="text-"]),li:not([class*="text-"])){color:#e8dfd7}
        body[data-admin-theme=dark] .workspace .dashboard :is(.dashboard-title,.stat-value,.panel-title,.sales-label,.top-product-name,.orders-table .total){color:#eee6df}
        body[data-admin-theme=dark] .workspace .dashboard :is(.stat-label,.top-qty,.orders-table td){color:#c9bbb0}
        body[data-admin-theme=dark] .workspace .dashboard :is(.stat-card,.panel,.recent-panel){background:#241e1a;border-color:#40352f}
        body[data-admin-theme=dark] .workspace .dashboard :is(.sales-chart,.recent-heading,.orders-table td){border-color:#40352f}
        body[data-admin-theme=dark] .workspace .dashboard .sales-chart{background:repeating-linear-gradient(to bottom,transparent 0,transparent 42px,#40352f 43px)}
        body[data-admin-theme=dark] .workspace .dashboard .top-item{border-color:#40352f}
        body[data-admin-theme=dark] .workspace .dashboard .orders-table thead{background:#302721;color:#e8dfd7}
        body[data-admin-theme=dark] .workspace .dashboard .orders-table tbody tr:hover{background:#302721}
        @yield('admin-styles')
    </style>
</head>
<body>
@php
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'box' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>',
        'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'dollar' => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9a2.5 2.5 0 0 0-2.5-1.5c-1.4 0-2.5.8-2.5 2s1.1 1.7 2.5 2 2.5.8 2.5 2-1.1 2-2.5 2A2.5 2.5 0 0 1 9.5 15M12 6v1.5M12 16.5V18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'badge' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M7 18c.8-2 2.6-3 5-3s4.2 1 5 3"/>',
        'bike' => '<circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM12 17.5V14l-3-3 4-3 2 3h3"/>',
        'bag' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'truck' => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
        'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/>',
    ];
    $groups = [
        'Overview' => [['Dashboard', route('admin.dashboard'), 'admin', 'grid']],
        'Catalog' => [['Products', route('admin.products.index'), 'admin/products*', 'box'], ['Categories', route('admin.categories.index'), 'admin/categories*', 'folder'], ['Promos', route('admin.promos.index'), 'admin/promos*', 'tag'], ['Discount & Fees', route('admin.settings.edit'), 'admin/settings*', 'dollar']],
        'People' => [['Customers', route('admin.users.index', 'customer'), 'admin/users/customer*', 'users'], ['Staff', route('admin.users.index', 'staff'), 'admin/users/staff*', 'badge'], ['Riders', route('admin.users.index', 'rider'), 'admin/users/rider*', 'bike']],
        'Operations' => [['Orders', route('admin.orders.index'), 'admin/orders*', 'bag'], ['Deliveries', route('admin.deliveries.index'), 'admin/deliveries*', 'truck'], ['GPS Monitor', route('admin.gps.index'), 'admin/gps-monitor*', 'pin'], ['Payments', route('admin.payments.index'), 'admin/payments*', 'card'], ['Reviews', route('admin.reviews.index'), 'admin/reviews*', 'star'], ['Reports', route('admin.reports.show', 'sales'), 'admin/reports*', 'chart'], ['Activity Logs', route('admin.activity.index'), 'admin/activity-logs*', 'file'], ['Customer Support', route('admin.support.index'), 'admin/support*', 'users']],
    ];
    $svg = fn (string $key, string $class = '') => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$key] ?? '') . '</svg>';
    $user = auth()->user();
@endphp
<div class="admin-shell" id="adminShell">
    <div class="mobile-backdrop" id="mobileBackdrop"></div>
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="KJM Coffee Shop admin home">
            <span class="brand-mark"><img src="{{ asset('logo1.png') }}" alt="KJM Coffee Shop logo"></span>
            <span class="brand-copy"><span class="brand-name">KJM Coffee Shop</span><span class="brand-tagline"></span></span>
        </a>
        <nav class="admin-nav" aria-label="Admin navigation">
            @foreach ($groups as $label => $items)
                <div class="nav-group">
                    <p class="nav-heading">{{ $label }}</p>
                    @foreach ($items as [$text, $url, $pattern, $icon])
                        @php $active = request()->is($pattern); @endphp
                        <a href="{{ $url }}" class="nav-link {{ $active ? 'active' : '' }}" title="{{ $text }}" @if($text === 'Customer Support') data-support-nav="1" @endif>
                            <span class="nav-icon">{!! $svg($icon) !!}</span><span class="nav-label">{{ $text }}</span>
                            @if ($text === 'Customer Support') <span id="supportUnreadBadge" class="support-unread-badge" hidden>0</span> @endif
                            @if ($active) <span class="nav-active-dot"></span> @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>
    <div class="admin-content">
        <header class="topbar">
            <button type="button" class="icon-button" id="sidebarToggle" aria-label="Toggle navigation">{!! $svg('menu') !!}</button>
            <label class="search-wrap">{!! $svg('search') !!}<input id="adminSearch" type="search" placeholder="Search navigation..." aria-label="Search navigation"></label>
            <span class="topbar-spacer"></span><span class="topbar-leaves" aria-hidden="true"></span>
            <a href="{{ route('admin.support.index') }}" class="icon-button support-bell-link" id="adminSupportBell" aria-label="Customer support notifications">{!! $svg('bell') !!}<span id="supportBellBadge" class="support-bell-badge" hidden>0</span></a>
            <button type="button" class="admin-account account-trigger" id="adminProfileOpen" aria-label="Open profile settings"><span class="account-avatar">@if($user?->avatar)<img src="{{ asset('storage/'.$user->avatar) }}" alt="">@else{{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}@endif</span><span class="account-copy"><span class="account-name">{{ $user->name ?? 'Admin' }}</span><span class="account-role">Administrator</span></span><span class="account-chevron" aria-hidden="true">&#8964;</span></button>
        </header>
        <main class="workspace">
            <div class="workspace-heading"><div><div class="eyebrow">Admin Workspace</div><div class="breadcrumb">{!! $svg('home') !!}<strong>@yield('title', 'Dashboard')</strong></div></div><span class="system-status"><i></i>System Active</span></div>
            @if (session('success')) <div class="flash flash-success">{{ session('success') }}</div> @endif
            @if (session('error')) <div class="flash flash-error">{{ session('error') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
<div class="admin-profile-shade" id="adminProfileShade" aria-hidden="true"></div>
<aside class="admin-profile-panel" id="adminProfilePanel" aria-label="Administrator menu">
    <div class="admin-panel-head">
        <div class="admin-panel-avatar">
            @if($user?->avatar)<img src="{{ asset('storage/'.$user->avatar) }}" alt="">@else{{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}@endif
        </div>
        <div class="admin-panel-head-copy">
            <strong>{{ $user->name ?? 'Admin' }}</strong>
            <small>Administrator</small>
        </div>
    </div>
    <a class="admin-menu-link" href="{{ route('admin.profile.edit') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.5-3.4 2.8-5.2 7-5.2s6.5 1.8 7 5.2"/></svg><span>My Profile</span>
    </a>
    <a class="admin-menu-link" href="{{ route('admin.profile.edit') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.2 2.3 2.6.4.7 2.5 2.2 1.5-.7 2.6.7 2.5-2.2 1.5-.7 2.5-2.6.4L12 21l-1.2-2.3-2.6-.4-.7-2.5-2.2-1.5.7-2.5-.7-2.6 2.2-1.5.7-2.5 2.6-.4z"/><circle cx="12" cy="12" r="2.5"/></svg><span>Profile Settings</span><span style="margin-left:auto;color:#8b8179">&rsaquo;</span>
    </a>
    <a class="admin-menu-link" href="{{ route('admin.settings.edit') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="17" r="2"/></svg><span>Settings</span>
    </a>
    <a class="admin-menu-link" href="{{ route('admin.profile.edit') }}#security">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v5c0 5-3.3 8.2-8 10-4.7-1.8-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></svg><span>Security</span>
    </a>
    <div class="admin-menu-separator"></div>
    <a class="admin-menu-link" href="{{ route('admin.profile.edit') }}#about">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>About</span>
    </a>
    <a class="admin-menu-link" href="{{ route('admin.settings.edit') }}#customer-service">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="12" width="4" height="7" rx="2"/><rect x="17" y="12" width="4" height="7" rx="2"/><path d="M17 19c-.8 1.3-2.4 2-5 2"/></svg><span>Help &amp; Support</span>
    </a>
    <div class="admin-menu-separator"></div>
    <form method="POST" action="{{ route('logout') }}">@csrf
        <button type="submit" class="admin-menu-link admin-menu-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Sign out</span>
        </button>
    </form>
</aside>
<script>
(()=>{const shell=document.getElementById('adminShell'),toggle=document.getElementById('sidebarToggle'),backdrop=document.getElementById('mobileBackdrop'),search=document.getElementById('adminSearch');toggle?.addEventListener('click',()=>{if(innerWidth<=850){shell.classList.toggle('mobile-open')}else{shell.classList.toggle('is-collapsed');localStorage.setItem('adminSidebarCollapsed',shell.classList.contains('is-collapsed')?'1':'0')}});backdrop?.addEventListener('click',()=>shell.classList.remove('mobile-open'));if(innerWidth>850&&localStorage.getItem('adminSidebarCollapsed')==='1')shell.classList.add('is-collapsed');search?.addEventListener('input',()=>{const q=search.value.toLowerCase();document.querySelectorAll('.admin-nav .nav-link').forEach(link=>link.style.display=link.textContent.toLowerCase().includes(q)?'':'none');document.querySelectorAll('.nav-group').forEach(group=>group.style.display=[...group.querySelectorAll('.nav-link')].some(link=>link.style.display!=='none')?'':'none')})})();
</script>
<script>
(()=>{
  const body=document.body,panel=document.getElementById('adminProfilePanel');
  const theme=value=>{body.dataset.adminTheme=value;localStorage.setItem('adminTheme',value);document.querySelectorAll('[data-admin-theme-choice]').forEach(button=>button.classList.toggle('active',button.dataset.adminThemeChoice===value))};
  theme(localStorage.getItem('adminTheme')||'light');
  document.querySelectorAll('[data-admin-theme-choice]').forEach(button=>button.addEventListener('click',()=>theme(button.dataset.adminThemeChoice)));
  const open=()=>{body.classList.add('admin-profile-open');document.getElementById('adminProfileOpen').setAttribute('aria-expanded','true')};
  const close=()=>{body.classList.remove('admin-profile-open');document.getElementById('adminProfileOpen').setAttribute('aria-expanded','false')};
  document.getElementById('adminProfileOpen')?.addEventListener('click',open);
  document.getElementById('adminProfileClose')?.addEventListener('click',close);
  document.getElementById('adminProfileShade')?.addEventListener('click',close);
  document.addEventListener('keydown',event=>{if(event.key==='Escape')close()});
})();
</script>
<style>
.support-unread-badge{margin-left:auto;min-width:21px;height:21px;padding:0 6px;border-radius:999px;background:#e44336;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800}.support-unread-badge[hidden],.support-bell-badge[hidden]{display:none}.support-bell-link{position:relative;text-decoration:none}.support-bell-badge{position:absolute;top:0;right:0;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:#e44336;color:white;font-size:10px;line-height:17px;text-align:center;font-weight:800}.support-toast{position:fixed;right:22px;bottom:22px;z-index:1000;width:min(370px,calc(100vw - 28px));padding:15px 17px;background:#fffdfb;color:#30231d;border:1px solid #eaded4;border-left:4px solid #b86a2c;border-radius:14px;box-shadow:0 16px 40px #29190f30;opacity:0;transform:translateY(10px);pointer-events:none;transition:.2s}.support-toast.visible{opacity:1;transform:translateY(0);pointer-events:auto}.support-toast a{color:inherit;text-decoration:none;display:block}.support-toast-title{font-weight:800;margin-bottom:4px}.support-toast-preview{font-size:13px;color:#75685f;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>
<script>
(()=>{
  const endpoint=@json(route('admin.support.notifications'));
  const navBadge=document.getElementById('supportUnreadBadge');
  const bellBadge=document.getElementById('supportBellBadge');
  const alertButton=document.getElementById('enableSupportAlerts');
  const storageKey='kjm-admin-last-support-message';
  let initialized=false;
  let toastTimer;
  const toast=document.createElement('div');
  toast.className='support-toast';
  toast.setAttribute('role','status');
  document.body.appendChild(toast);
  const setBadge=(element,count)=>{if(!element)return;element.textContent=count>99?'99+':String(count);element.hidden=count<1};
  const announce=latest=>{
    toast.innerHTML='';
    const link=document.createElement('a');link.href=latest.url;
    const title=document.createElement('div');title.className='support-toast-title';title.textContent='New customer support message';
    const preview=document.createElement('div');preview.className='support-toast-preview';preview.textContent=latest.customer+': '+latest.preview;
    link.append(title,preview);toast.appendChild(link);toast.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('visible'),9000);
    if('Notification' in window && Notification.permission==='granted'){
      const notification=new Notification('New customer support message',{body:latest.customer+': '+latest.preview,tag:'support-'+latest.conversation_id});
      notification.onclick=()=>{window.focus();location.href=latest.url;notification.close()};
    }
  };
  const poll=async()=>{
    try{
      const response=await fetch(endpoint,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
      if(!response.ok)return;
      const data=await response.json();setBadge(navBadge,data.unread_count||0);setBadge(bellBadge,data.unread_count||0);
      const latest=data.latest;
      const previous=localStorage.getItem(storageKey);
      if(!latest){initialized=true;return}
      if(!initialized){
        if(previous && Number(latest.message_id)>Number(previous))announce(latest);
        if(!previous)localStorage.setItem(storageKey,String(latest.message_id));
        initialized=true;return;
      }
      if(!previous || Number(latest.message_id)>Number(previous)){
        announce(latest);localStorage.setItem(storageKey,String(latest.message_id));
      }
    }catch(_){/* keep the admin screen usable if the server is temporarily offline */}
  };
  alertButton?.addEventListener('click',async()=>{
    if(!('Notification' in window)){alertButton.textContent='Desktop alerts unavailable';return}
    const permission=await Notification.requestPermission();
    alertButton.textContent=permission==='granted'?'Desktop alerts enabled':'Desktop alerts blocked';
  });
  poll();setInterval(poll,4000);
})();
</script>
@stack('scripts')
</body>
</html>

