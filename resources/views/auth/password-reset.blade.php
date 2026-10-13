<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password | KJM Coffee</title>
    <style>
        body{font:16px Arial,sans-serif;background:#faf7f2;color:#352019;margin:0;padding:24px}
        main{max-width:440px;margin:8vh auto;background:white;padding:28px;border-radius:18px;box-shadow:0 8px 30px #35201918}
        label{display:block;margin:16px 0 6px}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d8ccc2;border-radius:8px;font-size:16px}
        button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:9px;background:#4b2e25;color:white;font-size:16px}.error{color:#b42318}
    </style>
</head>
<body><main>
    <h1>Reset your password</h1>
    <p>Choose a new password for your KJM Coffee account.</p>
    @if ($errors->any()) <div class="error">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('password.reset.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
        <button type="submit">Save new password</button>
    </form>
</main></body>
</html>
