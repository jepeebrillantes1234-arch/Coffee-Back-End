<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot password | KJM Coffee Shop</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:'DM Sans',sans-serif;color:#382117;background:#fcf9f5 url('{{ asset('background1.png') }}') center/cover fixed no-repeat;display:grid;place-items:center;padding:24px}
        body:before{content:'';position:fixed;inset:0;background:rgba(45,25,14,.23)}main{position:relative;width:min(100%,480px);padding:34px;border:1px solid rgba(255,255,255,.8);border-radius:24px;background:rgba(255,250,242,.96);box-shadow:0 22px 65px rgba(25,12,6,.28)}
        .brand{text-align:center;margin-bottom:24px}.logo{width:82px;height:82px;object-fit:contain;border-radius:50%;background:white;border:1px solid #ead9c6;padding:5px}.brand h1{font:700 25px 'Playfair Display',serif;color:#3d2115;margin:12px 0 4px}.brand p,.hint{color:#806a5a;font-size:13px;line-height:1.5}
        label{display:block;margin:17px 0 7px;font-size:12px;font-weight:700;color:#503323}input{width:100%;height:46px;padding:0 13px;border:1px solid #d9c5b1;border-radius:11px;background:#fff;color:#342117;font:inherit;font-size:14px}input:focus{outline:3px solid rgba(168,102,56,.16);border-color:#a86638}
        button,.secondary{display:flex;align-items:center;justify-content:center;width:100%;min-height:46px;margin-top:18px;border:0;border-radius:12px;background:linear-gradient(100deg,#4a2617,#643519);color:#fffaf5;font:600 14px 'DM Sans',sans-serif;text-decoration:none;cursor:pointer}.secondary{margin-top:12px;background:transparent;border:1px solid #d9c5b1;color:#60351f}
        .alert{padding:10px 12px;border-radius:10px;margin:0 0 14px;background:#eef8f0;border:1px solid #bee4c6;color:#27643a;font-size:13px}.error{padding:10px 12px;border-radius:10px;margin:0 0 14px;background:#fff0ee;border:1px solid #f2cbc5;color:#a72b22;font-size:13px}.back{display:block;text-align:center;margin-top:20px;color:#70452c;font-size:13px;text-decoration:none}
        @media(max-width:520px){main{padding:26px 22px}}
    </style>
</head>
<body>
<main>
    <header class="brand">
        <img class="logo" src="{{ asset('logo1.png') }}" alt="KJM Coffee Shop">
        <h1>KJM Coffee Shop</h1>
        <p>Reset your password</p>
    </header>
    @if (session('status')) <div class="alert" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="error" role="alert">{{ $errors->first() }}</div> @endif

    @if ($email)
        <p class="hint">Ilagay ang 6-digit code na ipinadala sa email mo, pagkatapos pumili ng bagong password.</p>
        <form method="POST" action="{{ route('password.request.verify') }}">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <label for="code">Verification code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required placeholder="000000" value="{{ old('code') }}">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Verify code &amp; save password</button>
        </form>
        <form method="POST" action="{{ route('password.request.change-email') }}">
            @csrf
            <button class="secondary" type="submit">Use another email</button>
        </form>
    @else
        <p class="hint">Ilagay ang email address ng account mo. Magpapadala kami ng verification code para makapag-set ka ng bagong password.</p>
        <form method="POST" action="{{ route('password.request.code') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <button type="submit">Send verification code</button>
        </form>
    @endif
    <a class="back" href="{{ route('login') }}">Back to login</a>
</main>
</body>
</html>
