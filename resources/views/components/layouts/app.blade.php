@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold text-indigo-600">{{ config('app.name') }}</a>

            <div class="flex flex-wrap items-center gap-4 text-sm font-medium">
                @auth
                    <span class="text-slate-500">Hi, {{ auth()->user()->name }}</span>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-indigo-600">Admin panel</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer hover:text-indigo-600">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:text-indigo-600">Login</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Register</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-6xl grow px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white py-4 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</body>
</html>
