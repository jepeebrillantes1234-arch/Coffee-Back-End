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
        .error-shell{width:min(100%,560px)}.brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px;color:var(--ink)}.brand img{width:36px;height:36px;object-fit:contain}.brand-name{font-family:Georgia,"Times New Roman",serif;font-size:16px;font-weight:700}
        .error-card{position:relative;overflow:hidden;background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:clamp(20px,4vw,30px);box-shadow:var(--shadow)}.error-card:before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,#573a2b,#bd8a5b,#573a2b)}
        .error-mark{display:grid;width:38px;height:38px;margin:0 0 12px;place-items:center;border:1px solid var(--line);border-radius:50%;background:var(--accent-soft);color:var(--accent);font-size:20px;font-weight:800}
        .error-code{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:var(--accent-soft);color:var(--accent);font-size:12px;font-weight:750;letter-spacing:.08em}.error-code:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
        h1{margin:12px 0 8px;font-family:Georgia,"Times New Roman",serif;font-size:clamp(24px,4vw,31px);line-height:1.15;letter-spacing:-.025em}.error-description{max-width:570px;margin:0;color:var(--muted);font-size:15px;line-height:1.6}
        .recovery-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:19px}.button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;padding:0 16px;border:1px solid transparent;border-radius:10px;background:var(--coffee);color:#fff;text-decoration:none;font:inherit;font-size:14px;font-weight:650;cursor:pointer;transition:transform .15s,background .15s}.button:hover{transform:translateY(-1px);background:#3f2b20}.button-secondary{border-color:var(--line);background:var(--surface);color:var(--ink)}.button-secondary:hover{background:var(--page)}.button:focus-visible{outline:3px solid #bd8a5b;outline-offset:3px}
        @media(prefers-color-scheme:dark){html:not([data-theme="light"]){color-scheme:dark;--page:#151311;--surface:#211e1a;--ink:#f2ede7;--muted:#b9afa5;--line:#3d3730;--coffee:#ead8c7;--accent:#d7aa82;--accent-soft:#352a22;--shadow:0 20px 60px #0005}html:not([data-theme="light"]) .button{color:#211d19}html:not([data-theme="light"]) .button-secondary{color:var(--ink)}html:not([data-theme="light"]) .brand img{background:#f8f3ed}}
        html[data-theme="dark"]{color-scheme:dark;--page:#151311;--surface:#211e1a;--ink:#f2ede7;--muted:#b9afa5;--line:#3d3730;--coffee:#ead8c7;--accent:#d7aa82;--accent-soft:#352a22;--shadow:0 20px 60px #0005}html[data-theme="dark"] .button{color:#211d19}html[data-theme="dark"] .button-secondary{color:var(--ink)}html[data-theme="dark"] .brand img{background:#f8f3ed}
        @media(max-width:520px){body{padding:16px}.brand{margin-bottom:15px}.brand img{width:40px;height:40px}.brand-name{font-size:17px}.error-card{border-radius:19px}.error-description{font-size:15px}.recovery-actions{display:grid;grid-template-columns:1fr}.button{width:100%}}
    </style>
</head>
<body>
    <main class="error-shell">
        <div class="brand" aria-label="KJM Coffee Shop"><img src="{{ asset('logo1.png') }}" alt=""><span class="brand-name">KJM Coffee Shop</span></div>
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-mark" aria-hidden="true">!</div>
            <span class="error-code">@yield('code')</span>
            <h1 id="error-title">Unable to Load Page</h1>
            <p class="error-description">Something went wrong while loading this page. Would you like to try again?</p>
            <div class="recovery-actions" aria-label="Page recovery actions">
                <button class="button" id="error-refresh" type="button" data-fallback-url="{{ $homeUrl }}">Refresh</button>
                <button class="button button-secondary" type="button" data-go-back data-fallback-url="{{ $homeUrl }}">Go Back</button>
            </div>
        </section>
    </main>
    @include('components.request-loading-overlay')
    <script>
        const safePreviousPage = () => {
            try {
                const referrer = new URL(document.referrer);
                if (referrer.origin === window.location.origin && referrer.href !== window.location.href) {
                    return referrer.href;
                }
            } catch (_) {}
            return null;
        };

        const failedRequestMethod = @json(request()->method());
        document.getElementById('error-refresh')?.addEventListener('click', (event) => {
            const retryTarget = failedRequestMethod === 'GET'
                ? window.location.href
                : (safePreviousPage() || event.currentTarget.dataset.fallbackUrl);
            window.kjmStartRequestLoading?.();
            window.location.assign(retryTarget);
        });

        document.querySelectorAll('[data-go-back]').forEach((button) => button.addEventListener('click', () => {
            const previous = safePreviousPage();
            if (previous) window.location.assign(previous);
            else window.location.assign(button.dataset.fallbackUrl);
        }));
    </script>
</body>
</html>
