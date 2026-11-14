<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KJM Coffee Shop') | KJM Coffee Shop</title>
    <script>
        (() => {
            try {
                const theme = localStorage.getItem('adminTheme') || localStorage.getItem('staff-theme');
                if (theme === 'dark' || theme === 'light') document.documentElement.dataset.theme = theme;
            } catch (_) {}
        })();
    </script>
    <style>
        :root{color-scheme:light;--page:#f7f5f1;--surface:#fff;--ink:#211d19;--muted:#706a63;--line:#e8e2da;--coffee:#573a2b;--accent:#895b3d;--accent-soft:#f4ece5;--shadow:0 20px 60px #35271b12}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--page);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;display:grid;place-items:center;padding:24px}
        .error-shell{width:min(100%,760px)}.brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:22px;color:var(--ink);text-decoration:none}.brand img{width:46px;height:46px;object-fit:contain;border-radius:50%;background:#fff;padding:3px;border:1px solid var(--line)}.brand-name{font-family:Georgia,"Times New Roman",serif;font-size:19px;font-weight:700}
        .error-card{position:relative;overflow:hidden;background:var(--surface);border:1px solid var(--line);border-radius:24px;padding:clamp(26px,6vw,54px);box-shadow:var(--shadow)}.error-card:before{content:"";position:absolute;inset:0 0 auto;height:5px;background:linear-gradient(90deg,#573a2b,#bd8a5b,#573a2b)}
        .error-code{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:750;letter-spacing:.08em}.error-code:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
        h1{margin:18px 0 10px;font-family:Georgia,"Times New Roman",serif;font-size:clamp(29px,5vw,42px);line-height:1.13;letter-spacing:-.025em}.error-description{max-width:570px;margin:0;color:var(--muted);font-size:16px;line-height:1.7}.recovery-note{margin:23px 0 0;padding:15px 17px;border:1px solid var(--line);border-radius:14px;background:color-mix(in srgb,var(--surface),var(--page) 52%);color:var(--muted);font-size:14px;line-height:1.65}
        .recovery-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px}.button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;padding:0 17px;border:1px solid transparent;border-radius:12px;background:var(--coffee);color:#fff;text-decoration:none;font:inherit;font-size:14px;font-weight:650;cursor:pointer;transition:transform .15s,background .15s}.button:hover{transform:translateY(-1px);background:#3f2b20}.button-secondary{border-color:var(--line);background:var(--surface);color:var(--ink)}.button-secondary:hover{background:var(--page)}.button:focus-visible{outline:3px solid #bd8a5b;outline-offset:3px}.footer{margin:18px 0 0;text-align:center;color:var(--muted);font-size:12px}
        @media(prefers-color-scheme:dark){html:not([data-theme="light"]){color-scheme:dark;--page:#151311;--surface:#211e1a;--ink:#f2ede7;--muted:#b9afa5;--line:#3d3730;--coffee:#ead8c7;--accent:#d7aa82;--accent-soft:#352a22;--shadow:0 20px 60px #0005}html:not([data-theme="light"]) .button{color:#211d19}html:not([data-theme="light"]) .button-secondary{color:var(--ink)}html:not([data-theme="light"]) .brand img{background:#f8f3ed}}
        html[data-theme="dark"]{color-scheme:dark;--page:#151311;--surface:#211e1a;--ink:#f2ede7;--muted:#b9afa5;--line:#3d3730;--coffee:#ead8c7;--accent:#d7aa82;--accent-soft:#352a22;--shadow:0 20px 60px #0005}html[data-theme="dark"] .button{color:#211d19}html[data-theme="dark"] .button-secondary{color:var(--ink)}html[data-theme="dark"] .brand img{background:#f8f3ed}
        @media(max-width:520px){body{padding:16px}.brand{margin-bottom:15px}.brand img{width:40px;height:40px}.brand-name{font-size:17px}.error-card{border-radius:19px}.error-description{font-size:15px}.recovery-actions{display:grid;grid-template-columns:1fr}.button{width:100%}}
    </style>
</head>
<body>
    <main class="error-shell">
        <a class="brand" href="{{ $homeUrl }}" aria-label="KJM Coffee Shop home"><img src="{{ asset('logo1.png') }}" alt=""><span class="brand-name">KJM Coffee Shop</span></a>
        <section class="error-card" aria-labelledby="error-title">
            <span class="error-code">@yield('code')</span>
            <h1 id="error-title">@yield('heading')</h1>
            <p class="error-description">@yield('description')</p>
            @hasSection('note')
                <div class="recovery-note">@yield('note')</div>
            @endif
            <nav class="recovery-actions" aria-label="Recovery actions">@yield('actions')</nav>
        </section>
        <p class="footer">KJM Coffee Shop &middot; Thank you for your patience.</p>
    </main>
    <script>
        document.querySelectorAll('[data-go-back]').forEach((button) => button.addEventListener('click', () => {
            if (window.history.length > 1) window.history.back();
            else window.location.assign(button.dataset.fallbackUrl);
        }));
    </script>
</body>
</html>
