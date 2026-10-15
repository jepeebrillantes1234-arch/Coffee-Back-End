<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log in | KJM Coffee Shop</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;font-family:'DM Sans',sans-serif;color:#382117;background:#fcf9f5;display:flex;align-items:stretch}
        
        /* Split Screen Container */
        .split-container{display:flex;width:100%;min-height:100vh}
        
        /* Left Hero / Image Side */
        .hero-side{flex:1;position:relative;background:#3a2319 url('{{ asset('background.png') }}') center/cover no-repeat;display:flex;flex-direction:column;justify-content:flex-end;padding:48px;color:#fff;overflow:hidden}
        .hero-side:before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(35,20,12,.15) 0%,rgba(35,20,12,.75) 100%)}
        .hero-content{position:relative;z-index:1;max-width:480px}
        .hero-badge{display:inline-block;padding:6px 14px;background:rgba(255,255,255,.15);backdrop-filter:blur(8px);border-radius:20px;font-size:11px;letter-spacing:.15em;text-transform:uppercase;margin-bottom:16px;border:1px solid rgba(255,255,255,.2)}
        .hero-title{font:700 38px/1.15 'Playfair Display',serif;margin:0 0 12px;letter-spacing:.01em}
        .hero-description{font-size:14px;line-height:1.6;color:rgba(255,255,255,.85);margin:0}

        /* Right Form Side */
        .form-side{width:41%;max-width:620px;min-height:100vh;position:relative;display:flex;align-items:center;justify-content:center;padding:34px;background:#fbf0e2 url('{{ asset('background1.png') }}') center/cover no-repeat;box-shadow:-10px 0 30px rgba(0,0,0,.08)}
        .login-card{position:relative;z-index:1;width:100%;max-width:470px;padding:34px 32px;border:1px solid rgba(255,255,255,.72);border-radius:22px;background:rgba(255,250,242,.78);box-shadow:0 18px 50px rgba(70,39,20,.12);backdrop-filter:blur(5px)}

        /* Form elements */
        .brand{text-align:center;margin-bottom:28px}
        .brand-logo{width:92px;height:92px;border-radius:50%;display:grid;place-items:center;margin:0 auto 10px;background:#fff;border:1px solid #ead9c6;box-shadow:0 5px 18px rgba(69,39,21,.08);overflow:hidden}
        .brand-logo img{width:82px;height:82px;object-fit:contain}
        .brand-name{font:700 25px/1.12 'Playfair Display',serif;letter-spacing:.02em;color:#3d2115}
        .form-subtitle{text-align:center;color:#806a5a;font-size:13px;margin-top:6px}
        
        .field{margin-top:16px}
        .field-label{display:block;margin:0 0 7px;font-size:12px;font-weight:700;color:#503323}
        .input-wrap{height:46px;display:flex;align-items:center;gap:10px;padding:0 12px;border:1px solid #d9c5b1;border-radius:11px;background:rgba(255,255,255,.9);transition:border-color .18s,box-shadow .18s}
        .input-wrap:focus-within{border-color:#a86638;box-shadow:0 0 0 3px rgba(168,102,56,.13);background:#fff}
        .input-icon{width:18px;height:18px;color:#633d26;flex:none}
        .input-wrap input{width:100%;height:100%;min-width:0;border:0;outline:0;background:transparent;font:inherit;font-size:13px;color:#342117}
        .input-wrap input::placeholder{color:#9c8a7b}
        .password-toggle{border:0;background:transparent;color:#9b7352;padding:4px;display:grid;place-items:center;cursor:pointer}
        .password-toggle svg{width:18px;height:18px}
        
        .field-error{font-size:11px;color:#b42318;margin:5px 2px 0}
        .form-error{padding:10px 12px;margin:0 0 16px;border-radius:10px;background:#fff0ee;border:1px solid #f2cbc5;color:#a72b22;font-size:12px;line-height:1.45}
        
        .remember-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:16px 0 20px}
        .remember-label{display:flex;align-items:center;gap:8px;font-size:12px;color:#725e50;cursor:pointer}
        .remember-label input{width:15px;height:15px;accent-color:#60351f}
        .forgot-link{font-size:12px;font-weight:600;color:#70452c;text-decoration:none;white-space:nowrap}
        .forgot-link:hover{color:#402315;text-decoration:underline}
        .login-button{height:48px;width:100%;border:0;border-radius:12px;background:linear-gradient(100deg,#4a2617,#643519);color:#fffaf5;font:600 13px 'DM Sans',sans-serif;box-shadow:0 6px 14px rgba(62,32,17,.2);display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s}
        .login-button:hover{background:linear-gradient(100deg,#5b301c,#77421f);transform:translateY(-1px);box-shadow:0 9px 18px rgba(62,32,17,.23)}
        .login-button svg{width:16px;height:16px}

        /* Responsive Breakpoint */
        @media(max-width:900px){
            .hero-side{display:none}
            .form-side{width:100%;max-width:100%;min-height:100vh;padding:24px;background:transparent}
            body{background:#fbf0e2 url('{{ asset('background1.png') }}') center/cover fixed no-repeat}
            body:before{content:'';position:fixed;inset:0;background:rgba(35,20,12,.2);pointer-events:none}
            .login-card{background:rgba(255,250,242,.96);padding:28px;border-radius:24px;box-shadow:0 24px 70px rgba(25,12,6,.35);backdrop-filter:blur(8px)}
        }
    </style>
</head>
<body>
    <div class="split-container">
        <!-- Left Side: Image & Branding Showcase -->
        <div class="hero-side">
            <div class="hero-content">
                <span class="hero-badge">Admin & Staff Portal</span>
                <h1 class="hero-title">Good Coffee & Better Days Start Here.</h1>
                <p class="hero-description">Manage daily operations, track live orders, inspect delivery riders, and keep your coffee shop menu seamless and updated.</p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="form-side">
            <main class="login-card">
                <form method="POST" action="{{ route('login') }}" class="login-form">
                    @csrf
                    <header class="brand">
                        <div class="brand-logo"><img src="{{ asset('logo1.png') }}" alt="KJM Coffee Shop"></div>
                        <h1 class="brand-name">KJM Coffee Shop</h1>
                        <p class="form-subtitle">Sign in to your account</p>
                    </header>

                    @if ($errors->any())
                        <div class="form-error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <div class="field">
                        <label class="field-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="admin@kjmcoffee.test">
                        </div>
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                            <button class="password-toggle" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="remember-row">
                        <label class="remember-label"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Remember me</span></label>
                        <a class="forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
                    </div>

                    <button type="submit" class="login-button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></svg>
                        <span>Sign In</span>
                    </button>
                </form>
            </main>
        </div>
    </div>

    <script>
        (()=>{const input=document.getElementById('password'),button=document.getElementById('togglePassword'),icon=document.getElementById('eyeIcon');button?.addEventListener('click',()=>{const showing=input.type==='password';input.type=showing?'text':'password';button.setAttribute('aria-label',showing?'Hide password':'Show password');button.setAttribute('aria-pressed',showing?'true':'false');icon.innerHTML=showing?'<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a14.7 14.7 0 0 1-3.1 3.7M6.2 6.2C3.5 8.1 2 12 2 12s3.5 7 10 7a10.7 10.7 0 0 0 4-.8"/>':'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/>'})})();
    </script>
</body>
</html>
