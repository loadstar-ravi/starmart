@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }} Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="bg-slate-900 text-white">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-bold">{{ config('app.name') }} <span class="text-indigo-400">Admin</span></a>

            @auth
                <div class="flex flex-wrap items-center gap-4 text-sm font-medium">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-indigo-300">Dashboard</a>
                    <span class="text-slate-400">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer hover:text-indigo-300">Logout</button>
                    </form>
                </div>
            @endauth
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl grow px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
