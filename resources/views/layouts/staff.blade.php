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

<body class="staff-shell bg-cream text-espresso font-sans min-h-screen">

<style>

  :root{--staff-coffee:#38221b;--staff-coffee2:#573429;--staff-cream:#f7f3ee;--staff-line:#ece7e1;--staff-ink:#242b37;--staff-muted:#77808b}

  body.staff-shell{margin:0;background:var(--staff-cream);color:var(--staff-ink);font-family:Poppins,sans-serif}

  .staff-sidebar{position:fixed;inset:0 auto 0 0;width:252px;padding:0 14px;background:linear-gradient(160deg,#321d18,#4b2b22 72%,#2c1915);color:#fff;z-index:40;display:flex;flex-direction:column;transition:width .22s,transform .22s}

  .staff-brand{height:92px;display:flex;align-items:center;gap:12px;padding:14px 10px;border-bottom:1px solid #ffffff20;color:white;text-decoration:none}

  .staff-brand img{width:52px;height:52px;object-fit:contain;border-radius:50%;background:#fffaf4;padding:4px}

  .staff-brand strong{display:block;font-size:16px}.staff-brand small{display:block;color:#dbc8bc;margin-top:3px}

  .staff-nav{flex:1;padding-top:16px}.staff-nav small{display:block;padding:10px;color:#c6ac9c;font-size:10px;letter-spacing:.15em;text-transform:uppercase}

  .staff-nav a{display:flex;align-items:center;gap:12px;margin:4px 0;padding:13px 12px;border-radius:12px;color:#f8eee7;text-decoration:none;font-size:13px}

  .staff-nav a:hover,.staff-nav a.active{background:linear-gradient(90deg,#70412c,#8d512f)}.staff-nav .staff-nav-count{margin-left:auto;background:#e54848;padding:2px 8px;border-radius:20px;font-size:10px}

  body.staff-shell>header{position:sticky;top:0;z-index:20;margin-left:252px;background:#fffdfb;color:var(--staff-ink);border-bottom:1px solid var(--staff-line)}

  body.staff-shell>header>div{min-height:72px;padding:12px 24px;display:flex;align-items:center;gap:14px}

  .staff-menu-button{border:0;background:transparent;color:var(--staff-coffee);font-size:21px;cursor:pointer;padding:5px 9px}

  body.staff-shell>header nav{display:none}body.staff-shell>header>div>span:not(.staff-head-spacer){display:none}body.staff-shell>header>div>div.ml-auto{display:none}

  .staff-search{height:40px;width:min(430px,42vw);display:flex;align-items:center;gap:10px;padding:0 14px;border-radius:13px;background:#f4f0ec;color:#8a817a}

  .staff-search input{width:100%;border:0;outline:0;background:transparent;font:inherit;font-size:12px}

  .staff-head-spacer{flex:1}.staff-profile-trigger{border:0;background:transparent;display:flex;align-items:center;gap:10px;padding:5px 8px;cursor:pointer;color:#282e39}

  .staff-live-indicator{display:flex;align-items:center;gap:7px;border:1px solid #e8e1da;border-radius:999px;padding:6px 10px;color:#75685e;font-size:11px;white-space:nowrap}.staff-live-indicator i{width:8px;height:8px;border-radius:50%;background:#d49a36}.staff-live-indicator[data-connected="true"] i{background:#24a05a}body.staff-shell[data-staff-theme=dark] .staff-live-indicator{border-color:#51443b;color:#d5c9bd}

  .staff-sound-toggle svg{flex:0 0 auto}.staff-sound-toggle{display:inline-flex;align-items:center;gap:7px;border:1px solid #e8e1da;border-radius:999px;background:#fffdfb;color:#573429;padding:7px 12px;font-size:11px;font-weight:600;white-space:nowrap;cursor:pointer;transition:background .15s,border-color .15s,transform .15s}.staff-sound-toggle:hover{background:#fbf3eb;border-color:#c69a78;transform:translateY(-1px)}.staff-sound-toggle[aria-pressed="true"]{background:#edf8ef;border-color:#cde8d1;color:#237444}body.staff-shell[data-staff-theme=dark] .staff-sound-toggle{background:#241e1a;border-color:#51443b;color:#eee6df}

  .staff-notification-wrap{position:relative;flex:0 0 auto}.staff-notification-button{position:relative;display:grid;place-items:center;width:40px;height:40px;border:1px solid #eee7e0;border-radius:50%;background:#fffdfb;color:#573429;font-size:19px;cursor:pointer;transition:.15s}.staff-notification-button:hover,.staff-notification-button[aria-expanded="true"]{background:#f8eee5;border-color:#d9b99f}.staff-notification-badge{position:absolute;right:-3px;top:-4px;min-width:17px;height:17px;padding:0 4px;border-radius:99px;background:#dc4b3e;color:#fff;font-size:10px;font-weight:700;line-height:17px;text-align:center}.staff-notification-dropdown{position:fixed;top:78px;right:150px;z-index:90;width:min(360px,calc(100vw - 24px));max-height:min(70vh,500px);overflow:auto;border:1px solid #eee5dc;border-radius:17px;background:#fffdfb;color:#342017;box-shadow:0 18px 45px #30180f24}.staff-notification-dropdown[hidden]{display:none}.staff-notification-head{padding:15px 17px;border-bottom:1px solid #f0e8e1;font-weight:700}.staff-notification-entry{display:block;padding:13px 16px;border-bottom:1px solid #f4eee8;color:inherit;text-decoration:none}.staff-notification-entry:hover{background:#fbf5ef}.staff-notification-entry strong{display:block;font-size:13px}.staff-notification-entry small{display:block;margin-top:4px;color:#7c6c60;font-size:11px}.staff-notification-empty{padding:20px 16px;color:#807267;font-size:13px}.staff-notification-footer{display:block;padding:12px 16px;color:#8c4b25;text-align:center;text-decoration:none;font-size:12px;font-weight:700}

  body.staff-shell[data-staff-theme=dark] .staff-notification-button{background:#241e1a;border-color:#51443b;color:#eee6df}body.staff-shell[data-staff-theme=dark] .staff-notification-dropdown{background:#241e1a;color:#eee6df;border-color:#51443b}body.staff-shell[data-staff-theme=dark] .staff-notification-entry{border-color:#40352f}body.staff-shell[data-staff-theme=dark] .staff-notification-entry:hover{background:#302823}body.staff-shell[data-staff-theme=dark] .staff-notification-entry small{color:#b7aaa0}

  @media(max-width:800px){.staff-notification-dropdown{top:68px;right:12px}.staff-search{width:min(55vw,430px)}}

  .staff-head-avatar{width:38px;height:38px;overflow:hidden;border-radius:50%;background:#f0e1d4;color:#573429;display:grid;place-items:center;font-weight:700}.staff-head-avatar img{width:100%;height:100%;object-fit:cover}.staff-panel-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}

  body.staff-shell>main{margin-left:252px;min-height:calc(100vh - 72px);padding:26px;background:var(--staff-cream)}

  .staff-profile-shade{position:fixed;inset:0;background:#21150f75;z-index:60;opacity:0;visibility:hidden;transition:.2s}.staff-profile-panel{position:fixed;inset:0 0 0 auto;width:min(380px,94vw);padding:22px;background:white;z-index:61;transform:translateX(105%);transition:transform .24s;overflow:auto;box-shadow:-14px 0 40px #21150f20}

  .staff-profile-open .staff-profile-shade{opacity:1;visibility:visible}.staff-profile-open .staff-profile-panel{transform:translateX(0)}

  .staff-panel-head{display:flex;justify-content:space-between;align-items:center;font-weight:700}.staff-panel-close{border:0;background:#f3efeb;border-radius:10px;width:36px;height:36px;font-size:22px;cursor:pointer}

  .staff-panel-person{text-align:center;padding:24px}.staff-panel-avatar{width:76px;height:76px;border-radius:50%;margin:0 auto 10px;display:grid;place-items:center;background:#f0e1d4;color:#573429;font-size:25px;font-weight:700}
  .staff-avatar-upload{margin:12px 15px 16px;padding:14px;border:1px solid #eee7e0;border-radius:13px;background:#fffaf5}.staff-avatar-upload label{display:block;margin-bottom:8px;font-size:12px;font-weight:600;color:#38221b}.staff-avatar-upload label span{display:block;margin-top:3px;font-size:10px;font-weight:400;color:#77808b}.staff-avatar-upload input{display:block;width:100%;font-size:11px}.staff-avatar-upload button{margin-top:10px;padding:8px 13px;border:0;border-radius:9px;background:#75401f;color:#fff;font-size:12px;font-weight:600;cursor:pointer}.staff-avatar-error{margin-top:5px;color:#c33;font-size:11px}
  body.staff-shell[data-staff-theme=dark] .staff-avatar-upload{background:#302823;border-color:#51443b}body.staff-shell[data-staff-theme=dark] .staff-avatar-upload label{color:#eee6df}

  .staff-panel-card{margin:10px 0;padding:15px;border:1px solid #eee9e5;border-radius:15px;background:#fff}.staff-panel-label{font-size:11px;color:#77808b;margin:10px 0 4px}.staff-panel-value{font-size:13px;font-weight:600;overflow-wrap:anywhere}

  .staff-theme-row{display:flex;gap:8px}.staff-theme-row button{flex:1;padding:9px;border:1px solid #e8e3de;border-radius:10px;background:#fff;cursor:pointer}.staff-theme-row button.active{background:#573429;color:#fff}

  body.staff-shell[data-staff-theme=dark]{--staff-cream:#171412;--staff-ink:#eee6df;--staff-line:#40352f;color-scheme:dark}body.staff-shell[data-staff-theme=dark]>header,body.staff-shell[data-staff-theme=dark] .staff-profile-panel,body.staff-shell[data-staff-theme=dark] .staff-panel-card,body.staff-shell[data-staff-theme=dark] .bg-white,body.staff-shell[data-staff-theme=dark] [class~="bg-cream"]{background:#241e1a!important;color:#eee6df}body.staff-shell[data-staff-theme=dark]>main{background:#171412;color:#eee6df}body.staff-shell[data-staff-theme=dark] .staff-search{background:#332a25}body.staff-shell[data-staff-theme=dark] .staff-search input{color:#eee6df}body.staff-shell[data-staff-theme=dark] .staff-profile-trigger,body.staff-shell[data-staff-theme=dark] .staff-menu-button{color:#eee6df}

  body.staff-shell[data-staff-theme=dark] main :is([class*="text-espresso"],[class*="text-gray-"],[class*="text-black"]),body.staff-shell[data-staff-theme=dark] .staff-profile-panel :is(.text-gray-700,.text-amber-900){color:#e8dfd7!important}body.staff-shell[data-staff-theme=dark] .staff-panel-label{color:#b7aaa0}body.staff-shell[data-staff-theme=dark] main input:not([type=hidden]),body.staff-shell[data-staff-theme=dark] main select{background:#302823!important;color:#eee6df!important;border-color:#51443b!important}

  body.staff-shell[data-staff-theme=dark] main :is(table,th,td,hr){border-color:#463b35!important}body.staff-shell[data-staff-theme=dark] main thead{background:#302721;color:#e8dfd7}

  .staff-profile-panel{width:min(430px,96vw);padding:22px;background:#fffaf5}

  .staff-panel-head{margin-bottom:18px}.staff-panel-head strong{font-size:19px;color:#38221b}

  .staff-panel-person{display:flex;align-items:center;gap:16px;text-align:left;margin:14px 0;padding:18px;border:1px solid #eee7e0;border-radius:18px;background:#fff}

  .staff-panel-avatar{flex:0 0 76px;margin:0;width:76px;height:76px}

  .staff-active-badge{display:inline-flex;align-items:center;gap:6px;margin-top:7px;padding:5px 11px;border-radius:999px;background:#e5f6e9;color:#19743a;font-size:12px;font-weight:600}

  .staff-active-badge i{width:7px;height:7px;border-radius:50%;background:#24a05a}

  .staff-settings-card,.staff-details-card{margin:10px 0;padding:0;overflow:hidden}

  .staff-settings-row{display:flex;width:100%;min-height:72px;align-items:center;gap:13px;padding:12px 14px;color:#242b37;text-align:left;text-decoration:none;background:transparent;border:0;cursor:pointer}

  .staff-settings-row>span:nth-child(2){display:grid;gap:4px;flex:1}.staff-settings-row strong{font-size:13px;font-weight:600}.staff-settings-row small{font-size:11px;color:#77808b}.staff-settings-row>b{font-size:22px;color:#77808b;font-weight:400}

  .staff-settings-icon{display:grid;place-items:center;width:42px;height:42px;flex:0 0 42px;border-radius:50%;background:#faeadb;color:#573429;font-size:21px;font-weight:700}

  .staff-settings-row:hover{background:#fbf7f3}.staff-details-card summary{list-style:none}.staff-details-card summary::-webkit-details-marker{display:none}.staff-details-card[open] .staff-settings-row{border-bottom:1px solid #eee9e5}

  .staff-profile-details{padding:4px 18px 16px 70px}.staff-profile-details .staff-panel-label{margin-top:10px}

  .staff-theme-row{padding:0 14px 14px 69px}.staff-theme-row button{font-size:12px;color:#38221b}

  .staff-help-copy{padding:0 18px 16px 70px;font-size:12px;line-height:1.5;color:#77808b}

  .staff-logout-form{margin:18px 0}.staff-logout-form button{width:100%;display:flex;align-items:center;gap:12px;padding:15px 18px;border:1px solid #f6d8d2;border-radius:14px;background:#fff0ed;color:#d9382e;text-align:left;font-weight:600;cursor:pointer}.staff-logout-form button span{font-size:20px}

  body.staff-shell[data-staff-theme=dark] .staff-panel-head strong,body.staff-shell[data-staff-theme=dark] .staff-settings-row,body.staff-shell[data-staff-theme=dark] .staff-theme-row button{color:#eee6df}

  body.staff-shell[data-staff-theme=dark] .staff-panel-person,body.staff-shell[data-staff-theme=dark] .staff-settings-row:hover{background:#302823;border-color:#51443b}

  body.staff-shell[data-staff-theme=dark] .staff-settings-icon{background:#48352a;color:#f5d6b7}

  body.staff-shell[data-staff-theme=dark] .staff-logout-form button{background:#382320;border-color:#5b322d;color:#ffaaa0}

  @media(min-width:801px){body.staff-shell.staff-collapsed .staff-sidebar{width:82px;padding:0 10px}body.staff-shell.staff-collapsed .staff-brand{justify-content:center;padding:12px 0}body.staff-shell.staff-collapsed .staff-brand>span,body.staff-shell.staff-collapsed .staff-nav small,body.staff-shell.staff-collapsed .staff-nav a{justify-content:center;font-size:0;padding:13px 5px}body.staff-shell.staff-collapsed .staff-nav a>span:first-child{font-size:19px}body.staff-shell.staff-collapsed .staff-nav .staff-nav-count{font-size:9px;padding:2px 5px}body.staff-shell.staff-collapsed>header,body.staff-shell.staff-collapsed>main{margin-left:82px}}

  body.staff-shell>main h1{font-size:25px;font-weight:700;letter-spacing:-.03em;color:#202734;margin-bottom:18px}

  #queue{display:grid;gap:12px}#queue article{border:1px solid #eee9e5;border-radius:16px;padding:17px 18px;box-shadow:0 5px 20px #38221b08;transition:box-shadow .16s,transform .16s}#queue article:hover{box-shadow:0 9px 25px #38221b12;transform:translateY(-1px)}#queue article h2{font-size:14px;color:#202734}#queue article p{line-height:1.6}

  body.staff-shell>main input:not([type=hidden]),body.staff-shell>main select{border-radius:11px!important;border-color:#e4ddd6!important}body.staff-shell>main input:focus,body.staff-shell>main select:focus{outline:2px solid #c8843c55}

  body.staff-shell[data-staff-theme=dark] main h1,body.staff-shell[data-staff-theme=dark] main #queue article h2{color:#f0e9e2}

  @media(max-width:800px){.staff-sidebar{transform:translateX(-102%);transition:transform .2s}.staff-mobile-open .staff-sidebar{transform:translateX(0)}body.staff-shell>header,body.staff-shell>main{margin-left:0}.staff-mobile-shade{display:none;position:fixed;inset:0;background:#21150f75;z-index:35}.staff-mobile-open .staff-mobile-shade{display:block}body.staff-shell>header>div{padding:10px 13px}.staff-head-copy{display:none}}

</style>

@php

    $pending = \App\Models\Order::whereIn('status', ['awaiting_payment', 'pending'])->count();

    $tabs = [

        ['staff.orders',       'Orders',       'staff/orders*'],

        ['staff.pos',          'Walk-in POS',  'staff/pos*'],

        ['staff.menu',         'Menu / Sold out', 'staff/menu*'],

        ['staff.transactions', 'Transactions', 'staff/transactions*'],

    ];

@endphp

<aside class="staff-sidebar" id="staffSidebar">

    <a class="staff-brand" href="{{ route('staff.orders') }}"><img src="{{ asset('logo1.png') }}" alt="KJM Coffee Shop logo"><span><strong>Staff Panel</strong><small>Coffee Shop</small></span></a>

    <nav class="staff-nav"><small>Operations</small>

        @foreach ($tabs as [$route, $label, $pattern])

            <a href="{{ route($route) }}" class="{{ request()->is($pattern) ? 'active' : '' }}"><span>{{ $route === 'staff.orders' ? '▤' : ($route === 'staff.pos' ? '▣' : ($route === 'staff.menu' ? '♜' : '◷')) }}</span>{{ $label }}@if($route === 'staff.orders')<span id="nav-pending" class="staff-nav-count {{ $pending ? '' : 'hidden' }}">{{ $pending }}</span>@endif</a>

        @endforeach

    </nav>

</aside>

<div class="staff-mobile-shade" id="staffMobileShade"></div>

<header class="bg-espresso text-cream">

    <div class="px-5 py-3 flex flex-wrap items-center gap-4">

        <button type="button" id="staffMenuButton" class="staff-menu-button" aria-label="Open navigation">☰</button>

        <label class="staff-search"><span>⌕</span><input id="staffSearch" type="search" placeholder="Search order number, customer name..."></label>

        <span class="staff-head-spacer"></span>

        <div class="staff-notification-wrap">

            <button type="button" id="staffNotificationButton" class="staff-notification-button" aria-label="View pending order notifications" aria-expanded="false" aria-controls="staffNotificationDropdown" title="Pending orders"><svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><span id="staff-notification-count" class="staff-notification-badge {{ $pending ? '' : 'hidden' }}">{{ $pending }}</span></button>

            <div id="staffNotificationDropdown" class="staff-notification-dropdown" hidden><div class="staff-notification-head">Pending order notifications</div><div id="staffNotificationList"><div class="staff-notification-empty">Loading orders...</div></div><a class="staff-notification-footer" href="{{ route('staff.orders') }}">View order queue</a></div>

        </div>

        <div id="staff-live-indicator" class="staff-live-indicator" data-connected="false" role="status" aria-live="polite"><i></i><span>Connecting</span></div>

        <button type="button" id="staff-sound-toggle" class="staff-sound-toggle" aria-pressed="false"><svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18 5a9 9 0 0 1 0 14"/></svg><span data-sound-label>Sound off</span></button>

        <button type="button" id="staffProfileButton" class="staff-profile-trigger" aria-label="View staff profile and settings" title="View profile and settings"><span class="staff-head-avatar">@if(auth()->user()->avatar)<img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Staff profile photo">@else{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}@endif</span><span class="staff-head-copy"><strong>{{ auth()->user()->name }}</strong><small class="block text-xs text-gray-500">View profile</small></span><span></span></button>

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

<div class="staff-profile-shade" id="staffProfileShade"></div>

<aside class="staff-profile-panel" id="staffProfilePanel" aria-label="Staff settings">

    <div class="staff-panel-head">

        <div><strong>Staff Settings</strong><div class="staff-panel-label">Manage your profile and account settings.</div></div>

        <button class="staff-panel-close" id="staffProfileClose" type="button" aria-label="Close">×</button>

    </div>



    <section class="staff-panel-person">

        <div class="staff-panel-avatar">@if(auth()->user()->avatar)<img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Staff profile photo">@else{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}@endif</div>

        <div><strong class="block text-lg">{{ auth()->user()->name }}</strong><div class="staff-panel-label">{{ ucfirst(auth()->user()->role) }}</div>

            <span class="staff-active-badge"><i></i>{{ ucfirst(auth()->user()->status ?? 'active') }}</span>

        </div>

    </section>



    <details class="staff-panel-card staff-details-card">

        <summary class="staff-settings-row"><span class="staff-settings-icon">♙</span><span><strong>Profile Information</strong><small>Edit or review your account details</small></span><b>›</b></summary>

        <div class="staff-profile-details"><div class="staff-panel-label">Full Name</div><div class="staff-panel-value">{{ auth()->user()->name }}</div>

            <div class="staff-panel-label">Email Address</div><div class="staff-panel-value">{{ auth()->user()->email }}</div>

            @if(auth()->user()->phone)<div class="staff-panel-label">Phone Number</div><div class="staff-panel-value">{{ auth()->user()->phone }}</div>@endif

            <div class="staff-panel-label">Role</div><div class="staff-panel-value">{{ ucfirst(auth()->user()->role) }}</div>

        </div>

        <form method="POST" action="{{ route('staff.profile.avatar') }}" enctype="multipart/form-data" class="staff-avatar-upload">
            @csrf
            <label for="staff-avatar-file">Profile photo <span>(JPG, PNG, or WebP; max 2 MB)</span></label>
            <input id="staff-avatar-file" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
            @error('avatar')<p class="staff-avatar-error">{{ $message }}</p>@enderror
            <button type="submit">Upload photo</button>
        </form>
    </details>



    <section class="staff-panel-card staff-settings-card">

        <a class="staff-settings-row" href="{{ url('/forgot-password') }}"><span class="staff-settings-icon">♙</span><span><strong>Change Password</strong><small>Update your password</small></span><b>›</b></a>

    </section>

    <section class="staff-panel-card staff-settings-card">

        <button class="staff-settings-row" id="staffNotificationSettings" type="button"><span class="staff-settings-icon">♟</span><span><strong>Notification Settings</strong><small id="staffNotificationState">Manage order and system notifications</small></span><b>›</b></button>

    </section>

    <section class="staff-panel-card staff-settings-card">

        <div class="staff-settings-row staff-settings-heading"><span class="staff-settings-icon">☼</span><span><strong>Appearance</strong><small>Light / Dark mode and theme settings</small></span></div>

        <div class="staff-theme-row"><button type="button" data-theme-choice="light">☼ &nbsp; Light</button><button type="button" data-theme-choice="dark">☾ &nbsp; Dark</button></div>

    </section>

    <details class="staff-panel-card staff-details-card">

        <summary class="staff-settings-row"><span class="staff-settings-icon">?</span><span><strong>Help &amp; Support</strong><small>Get help or contact support</small></span><b>›</b></summary>

        <p class="staff-help-copy">For account access or order system issues, contact your system administrator.</p>

    </details>

    <form method="POST" action="{{ route('logout') }}" class="staff-logout-form">@csrf<button type="submit"><span>⇥</span> Log Out</button></form>

</aside>

<script>

(() => {

  const body = document.body;

  const liveIndicator = document.getElementById('staff-live-indicator');

  const soundToggle = document.getElementById('staff-sound-toggle');

  const orderAlertsUrl = @json(route('staff.orders.alerts'));

  const orderQueueUrl = @json(route('staff.orders'));

  const notificationButton = document.getElementById('staffNotificationButton');

  const notificationDropdown = document.getElementById('staffNotificationDropdown');

  const notificationList = document.getElementById('staffNotificationList');

  const notificationBadge = document.getElementById('staff-notification-count');

  let soundEnabled = localStorage.getItem('staff_sound') === '1';

  let audioContext = null;

  let countRefreshing = false;

  const paintSoundToggle = () => {

    soundToggle.querySelector('[data-sound-label]').textContent = soundEnabled ? 'Sound On' : 'Sound Off';

    soundToggle.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');

  };

  const playOrderSound = async () => {

    if (!soundEnabled) return;

    try {

      const AudioContextClass = window.AudioContext || window.webkitAudioContext;

      if (!AudioContextClass) return;

      audioContext = audioContext || new AudioContextClass();

      if (audioContext.state === 'suspended') await audioContext.resume();

      [880, 1175, 880].forEach((frequency, index) => {

        const oscillator = audioContext.createOscillator();

        const gain = audioContext.createGain();

        const startAt = audioContext.currentTime + index * 0.2;

        oscillator.frequency.value = frequency;

        gain.gain.value = 0.16;

        oscillator.connect(gain);

        gain.connect(audioContext.destination);

        oscillator.start(startAt);

        oscillator.stop(startAt + 0.14);

      });

    } catch (_) {}

  };

  paintSoundToggle();

  soundToggle.addEventListener('click', async () => {

    soundEnabled = !soundEnabled;

    localStorage.setItem('staff_sound', soundEnabled ? '1' : '0');

    paintSoundToggle();

    if (soundEnabled) await playOrderSound();

  });

  const updateConnection = connected => {

    liveIndicator.dataset.connected = connected ? 'true' : 'false';

    liveIndicator.querySelector('span').textContent = connected ? 'Online' : 'Reconnecting';

  };

  const refreshOrderCount = async () => {

    if (countRefreshing) return;

    countRefreshing = true;

    try {

      const response = await fetch(orderAlertsUrl, { cache: 'no-store', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

      if (!response.ok) return;

      const { count, items } = await response.json();

      const currentCount = Number(count) || 0;

      const previousValue = localStorage.getItem('staff_pending_count_seen');

      if (previousValue !== null && currentCount > Number(previousValue)) playOrderSound();

      localStorage.setItem('staff_pending_count_seen', String(currentCount));

      document.querySelectorAll('#nav-pending').forEach(badge => { badge.textContent = String(currentCount); badge.classList.toggle('hidden', currentCount < 1); });

      notificationBadge.textContent = String(currentCount);

      notificationBadge.classList.toggle('hidden', currentCount < 1);

      notificationList.replaceChildren();

      if (!items.length) {

        const empty = document.createElement('div'); empty.className = 'staff-notification-empty'; empty.textContent = 'No pending customer orders.'; notificationList.append(empty);

      } else items.forEach(item => {

        const link = document.createElement('a'); link.className = 'staff-notification-entry'; link.href = orderQueueUrl;

        const title = document.createElement('strong'); title.textContent = `${item.order_number} · ${item.status}`;

        const info = document.createElement('small'); info.textContent = `${item.customer} · ₱${Number(item.total).toFixed(2)} · ${item.created_at || 'Just now'}`;

        link.append(title, info); link.addEventListener('click', () => { notificationDropdown.hidden = true; notificationButton.setAttribute('aria-expanded', 'false'); }); notificationList.append(link);

      });

    } catch (_) {} finally { countRefreshing = false; }

  };

  notificationButton.addEventListener('click', () => {

    const willOpen = notificationDropdown.hidden; notificationDropdown.hidden = !willOpen;

    notificationButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false'); if (willOpen) refreshOrderCount();

  });

  document.addEventListener('click', event => { if (!event.target.closest('.staff-notification-wrap')) { notificationDropdown.hidden = true; notificationButton.setAttribute('aria-expanded', 'false'); } });

  window.addEventListener('live-connection', event => updateConnection(event.detail?.connected === true));

  window.addEventListener('live-update', event => {

    if (event.detail?.kind === 'order') refreshOrderCount();

  });

  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshOrderCount(); });

  refreshOrderCount();

  window.setInterval(() => { if (!document.hidden) refreshOrderCount(); }, 5000);



  const panel = document.getElementById('staffProfilePanel');

  const setTheme = value => {

    body.dataset.staffTheme = value;

    localStorage.setItem('staff-theme', value);

    document.querySelectorAll('[data-theme-choice]').forEach(button => button.classList.toggle('active', button.dataset.themeChoice === value));

  };

  setTheme(localStorage.getItem('staff-theme') || 'light');

  document.querySelectorAll('[data-theme-choice]').forEach(button => button.addEventListener('click', () => setTheme(button.dataset.themeChoice)));

  const open = () => body.classList.add('staff-profile-open');

  const close = () => body.classList.remove('staff-profile-open');

  const notificationSettings = document.getElementById('staffNotificationSettings');

  const notificationState = document.getElementById('staffNotificationState');

  if ('Notification' in window && Notification.permission === 'granted') notificationState.textContent = 'Notifications are enabled';

  notificationSettings.addEventListener('click', async () => {

    if (!('Notification' in window)) {

      notificationState.textContent = 'This browser does not support notifications';

      return;

    }

    const permission = await Notification.requestPermission();

    notificationState.textContent = permission === 'granted' ? 'Notifications are enabled' : 'Notifications are turned off';

  });

  document.getElementById('staffProfileButton').addEventListener('click', open);

  document.getElementById('staffProfileClose').addEventListener('click', close);

  document.getElementById('staffProfileShade').addEventListener('click', close);

  document.getElementById('staffMenuButton').addEventListener('click', () => {

    if (window.matchMedia('(max-width: 800px)').matches) body.classList.toggle('staff-mobile-open');

    else body.classList.toggle('staff-collapsed');

  });

  document.getElementById('staffMobileShade').addEventListener('click', () => body.classList.remove('staff-mobile-open'));

  document.querySelectorAll('.staff-nav a').forEach(link => link.addEventListener('click', () => body.classList.remove('staff-mobile-open')));

  document.addEventListener('keydown', event => { if (event.key === 'Escape') { body.classList.remove('staff-profile-open', 'staff-mobile-open'); notificationDropdown.hidden = true; notificationButton.setAttribute('aria-expanded', 'false'); } });

  const search = document.getElementById('staffSearch');

  search.addEventListener('input', () => {

    const query = search.value.trim().toLowerCase();

    document.querySelectorAll('#queue article, .item').forEach(card => card.classList.toggle('hidden', query !== '' && !card.textContent.toLowerCase().includes(query)));

  });

})();

</script>

</body>

</html>
