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
  .staff-side-footer{border-top:1px solid #ffffff20;padding:14px 8px}.staff-side-footer form{display:none}.staff-side-footer form button{border:0;background:transparent;color:#f5e7dc;padding:9px 4px;cursor:pointer}
  body.staff-shell>header{position:sticky;top:0;z-index:20;margin-left:252px;background:#fffdfb;color:var(--staff-ink);border-bottom:1px solid var(--staff-line)}
  body.staff-shell>header>div{min-height:72px;padding:12px 24px;display:flex;align-items:center;gap:14px}
  .staff-menu-button{border:0;background:transparent;color:var(--staff-coffee);font-size:21px;cursor:pointer;padding:5px 9px}
  body.staff-shell>header nav{display:none}body.staff-shell>header>div>span:not(.staff-head-spacer){display:none}body.staff-shell>header>div>div.ml-auto{display:none}
  .staff-search{height:40px;width:min(430px,42vw);display:flex;align-items:center;gap:10px;padding:0 14px;border-radius:13px;background:#f4f0ec;color:#8a817a}
  .staff-search input{width:100%;border:0;outline:0;background:transparent;font:inherit;font-size:12px}
  .staff-head-spacer{flex:1}.staff-profile-trigger{border:0;background:transparent;display:flex;align-items:center;gap:10px;padding:5px 8px;cursor:pointer;color:#282e39}
  .staff-head-avatar{width:38px;height:38px;border-radius:50%;background:#f0e1d4;color:#573429;display:grid;place-items:center;font-weight:700}
  body.staff-shell>main{margin-left:252px;min-height:calc(100vh - 72px);padding:26px;background:var(--staff-cream)}
  .staff-profile-shade{position:fixed;inset:0;background:#21150f75;z-index:60;opacity:0;visibility:hidden;transition:.2s}.staff-profile-panel{position:fixed;inset:0 0 0 auto;width:min(380px,94vw);padding:22px;background:white;z-index:61;transform:translateX(105%);transition:transform .24s;overflow:auto;box-shadow:-14px 0 40px #21150f20}
  .staff-profile-open .staff-profile-shade{opacity:1;visibility:visible}.staff-profile-open .staff-profile-panel{transform:translateX(0)}
  .staff-panel-head{display:flex;justify-content:space-between;align-items:center;font-weight:700}.staff-panel-close{border:0;background:#f3efeb;border-radius:10px;width:36px;height:36px;font-size:22px;cursor:pointer}
  .staff-panel-person{text-align:center;padding:24px}.staff-panel-avatar{width:76px;height:76px;border-radius:50%;margin:0 auto 10px;display:grid;place-items:center;background:#f0e1d4;color:#573429;font-size:25px;font-weight:700}
  .staff-panel-card{margin:10px 0;padding:15px;border:1px solid #eee9e5;border-radius:15px;background:#fff}.staff-panel-label{font-size:11px;color:#77808b;margin:10px 0 4px}.staff-panel-value{font-size:13px;font-weight:600;overflow-wrap:anywhere}
  .staff-theme-row{display:flex;gap:8px}.staff-theme-row button{flex:1;padding:9px;border:1px solid #e8e3de;border-radius:10px;background:#fff;cursor:pointer}.staff-theme-row button.active{background:#573429;color:#fff}
  body.staff-shell[data-staff-theme=dark]{--staff-cream:#171412;--staff-ink:#eee6df;--staff-line:#40352f;color-scheme:dark}body.staff-shell[data-staff-theme=dark]>header,body.staff-shell[data-staff-theme=dark] .staff-profile-panel,body.staff-shell[data-staff-theme=dark] .staff-panel-card,body.staff-shell[data-staff-theme=dark] .bg-white,body.staff-shell[data-staff-theme=dark] [class~="bg-cream"]{background:#241e1a!important;color:#eee6df}body.staff-shell[data-staff-theme=dark]>main{background:#171412;color:#eee6df}body.staff-shell[data-staff-theme=dark] .staff-search{background:#332a25}body.staff-shell[data-staff-theme=dark] .staff-search input{color:#eee6df}body.staff-shell[data-staff-theme=dark] .staff-profile-trigger,body.staff-shell[data-staff-theme=dark] .staff-menu-button{color:#eee6df}
  body.staff-shell[data-staff-theme=dark] main :is([class*="text-espresso"],[class*="text-gray-"],[class*="text-black"]),body.staff-shell[data-staff-theme=dark] .staff-profile-panel :is(.text-gray-700,.text-amber-900){color:#e8dfd7!important}body.staff-shell[data-staff-theme=dark] .staff-panel-label{color:#b7aaa0}body.staff-shell[data-staff-theme=dark] main input:not([type=hidden]),body.staff-shell[data-staff-theme=dark] main select{background:#302823!important;color:#eee6df!important;border-color:#51443b!important}
  body.staff-shell[data-staff-theme=dark] main :is(table,th,td,hr){border-color:#463b35!important}body.staff-shell[data-staff-theme=dark] main thead{background:#302721;color:#e8dfd7}
  @media(min-width:801px){body.staff-shell.staff-collapsed .staff-sidebar{width:82px;padding:0 10px}body.staff-shell.staff-collapsed .staff-brand{justify-content:center;padding:12px 0}body.staff-shell.staff-collapsed .staff-brand>span,body.staff-shell.staff-collapsed .staff-nav small,body.staff-shell.staff-collapsed .staff-side-footer>strong,body.staff-shell.staff-collapsed .staff-side-footer>div{display:none}body.staff-shell.staff-collapsed .staff-nav a{justify-content:center;font-size:0;padding:13px 5px}body.staff-shell.staff-collapsed .staff-nav a>span:first-child{font-size:19px}body.staff-shell.staff-collapsed .staff-nav .staff-nav-count{font-size:9px;padding:2px 5px}body.staff-shell.staff-collapsed .staff-side-footer form button{font-size:0}body.staff-shell.staff-collapsed .staff-side-footer form button:first-letter{font-size:16px}body.staff-shell.staff-collapsed>header,body.staff-shell.staff-collapsed>main{margin-left:82px}}
  body.staff-shell>main h1{font-size:25px;font-weight:700;letter-spacing:-.03em;color:#202734;margin-bottom:18px}
  #queue{display:grid;gap:12px}#queue article{border:1px solid #eee9e5;border-radius:16px;padding:17px 18px;box-shadow:0 5px 20px #38221b08;transition:box-shadow .16s,transform .16s}#queue article:hover{box-shadow:0 9px 25px #38221b12;transform:translateY(-1px)}#queue article h2{font-size:14px;color:#202734}#queue article p{line-height:1.6}
  body.staff-shell>main input:not([type=hidden]),body.staff-shell>main select{border-radius:11px!important;border-color:#e4ddd6!important}body.staff-shell>main input:focus,body.staff-shell>main select:focus{outline:2px solid #c8843c55}
  body.staff-shell[data-staff-theme=dark] main h1,body.staff-shell[data-staff-theme=dark] main #queue article h2{color:#f0e9e2}
  @media(max-width:800px){.staff-sidebar{transform:translateX(-102%);transition:transform .2s}.staff-mobile-open .staff-sidebar{transform:translateX(0)}body.staff-shell>header,body.staff-shell>main{margin-left:0}.staff-mobile-shade{display:none;position:fixed;inset:0;background:#21150f75;z-index:35}.staff-mobile-open .staff-mobile-shade{display:block}body.staff-shell>header>div{padding:10px 13px}.staff-head-copy{display:none}}
</style>
@php
    $pending = \App\Models\Order::where('status', 'pending')->count();
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
    <div class="staff-side-footer"><strong>{{ auth()->user()->name }}</strong><div class="text-xs text-white/60">{{ ucfirst(auth()->user()->role) }}</div><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">↪ &nbsp; Log out</button></form></div>
</aside>
<div class="staff-mobile-shade" id="staffMobileShade"></div>
<header class="bg-espresso text-cream">
    <div class="px-5 py-3 flex flex-wrap items-center gap-4">
        <button type="button" id="staffMenuButton" class="staff-menu-button" aria-label="Open navigation">☰</button>
        <label class="staff-search"><span>⌕</span><input id="staffSearch" type="search" placeholder="Search order number, customer name..."></label>
        <span class="staff-head-spacer"></span>
        <button type="button" id="staffProfileButton" class="staff-profile-trigger"><span class="staff-head-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="staff-head-copy"><strong>{{ auth()->user()->name }}</strong><small class="block text-xs text-gray-500">{{ ucfirst(auth()->user()->role) }}</small></span><span>⌄</span></button>
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
<aside class="staff-profile-panel" id="staffProfilePanel" aria-label="Profile settings">
    <div class="staff-panel-head"><span>⚙ &nbsp; Profile Settings</span><button class="staff-panel-close" id="staffProfileClose" type="button" aria-label="Close">×</button></div>
    <div class="staff-panel-person"><div class="staff-panel-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div><strong>{{ auth()->user()->name }}</strong><div class="staff-panel-label">{{ ucfirst(auth()->user()->role) }} · Online</div></div>
    <section class="staff-panel-card"><div class="staff-panel-label">Full Name</div><div class="staff-panel-value">{{ auth()->user()->name }}</div><hr class="my-3"><div class="staff-panel-label">Email Address</div><div class="staff-panel-value">{{ auth()->user()->email }}</div>@if(auth()->user()->phone)<hr class="my-3"><div class="staff-panel-label">Phone Number</div><div class="staff-panel-value">{{ auth()->user()->phone }}</div>@endif<div class="staff-panel-label">Role</div><div class="staff-panel-value">{{ ucfirst(auth()->user()->role) }}</div></section>
    <section class="staff-panel-card"><strong class="text-sm">☾ &nbsp; Preferences</strong><div class="staff-panel-label">Theme</div><div class="staff-theme-row"><button type="button" data-theme-choice="light">☼ &nbsp; Light</button><button type="button" data-theme-choice="dark">☾ &nbsp; Dark</button></div></section>
    <section class="staff-panel-card"><strong class="text-sm">◇ &nbsp; Security</strong><a class="block py-3 text-sm text-gray-700 no-underline hover:text-amber-800" href="{{ url('/forgot-password') }}">Change Password &nbsp; ›</a></section>
    @if(auth()->user()->role === 'admin')<a class="block px-2 py-3 text-sm text-amber-900" href="{{ route('admin.dashboard') }}">Open Admin Workspace &nbsp; ›</a>@endif
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button type="submit" class="w-full rounded-xl bg-espresso px-4 py-3 text-sm font-semibold text-white">↪ &nbsp; Log out</button></form>
</aside>
<script>
(() => {
  const body = document.body;
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
  document.getElementById('staffProfileButton').addEventListener('click', open);
  document.getElementById('staffProfileClose').addEventListener('click', close);
  document.getElementById('staffProfileShade').addEventListener('click', close);
  document.getElementById('staffMenuButton').addEventListener('click', () => {
    if (window.matchMedia('(max-width: 800px)').matches) body.classList.toggle('staff-mobile-open');
    else body.classList.toggle('staff-collapsed');
  });
  document.getElementById('staffMobileShade').addEventListener('click', () => body.classList.remove('staff-mobile-open'));
  document.querySelectorAll('.staff-nav a').forEach(link => link.addEventListener('click', () => body.classList.remove('staff-mobile-open')));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { body.classList.remove('staff-profile-open', 'staff-mobile-open'); } });
  const search = document.getElementById('staffSearch');
  search.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    document.querySelectorAll('#queue article, .item').forEach(card => card.classList.toggle('hidden', query !== '' && !card.textContent.toLowerCase().includes(query)));
  });
})();
</script>
</body>
</html>
