<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Coffee Shop</title>
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream font-sans min-h-screen flex items-center justify-center p-4">
    <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8 space-y-5">
        @csrf
        <div class="text-center">
            <div class="text-4xl">☕</div>
            <h1 class="text-xl font-semibold text-espresso mt-2">Coffee Shop Panel</h1>
            <p class="text-sm text-espresso/60">Admin at Staff login</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-espresso mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel">
            @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-espresso mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border border-espresso/20 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-caramel">
        </div>

        <label class="flex items-center gap-2 text-sm text-espresso/70">
            <input type="checkbox" name="remember" class="rounded"> Tandaan ako
        </label>

        <button class="w-full bg-espresso text-cream rounded-lg py-2.5 font-medium hover:bg-espresso/90">Login</button>
    </form>
</body>
</html>
