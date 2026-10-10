@props(['code', 'title'])

{{--
    Error pages stand on their own: no session, signed-in user or database is needed to show one,
    so they still work when the error is that one of those is missing.
--}}
{{-- Someone refused from the admin panel is sent to the shop, not back to the panel that refused them. --}}
@php($inAdmin = request()->is('admin', 'admin/*') && (int) $code !== 403)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-3">
            <a href="{{ url('/') }}" class="text-lg font-bold text-indigo-600">{{ config('app.name') }}</a>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-xl grow flex-col items-center justify-center px-4 py-16 text-center">
        <p class="text-sm font-semibold text-indigo-600">Error {{ $code }}</p>
        <h1 class="mt-2 text-3xl font-bold">{{ $title }}</h1>
        <p class="mt-3 text-slate-600">{{ $slot }}</p>

        <a href="{{ url($inAdmin ? '/admin' : '/') }}" class="mt-8 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            {{ $inAdmin ? 'Back to the admin dashboard' : 'Back to the home page' }}
        </a>
    </main>

    <footer class="border-t border-slate-200 bg-white py-4 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</body>
</html>
