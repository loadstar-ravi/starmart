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
                <a href="{{ route('products.index') }}" @class(['hover:text-indigo-600', 'text-indigo-600' => request()->routeIs('products.*')])>Products</a>
                @auth
                    @if (auth()->user()->isCustomer())
                        <a href="{{ route('cart.show') }}" @class(['inline-flex items-center gap-1.5 hover:text-indigo-600', 'text-indigo-600' => request()->routeIs('cart.*')])>
                            Cart
                            @if ($cartItemCount > 0)
                                <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">
                                    <span class="sr-only">Items in your cart:</span> <span data-cart-count>{{ $cartItemCount }}</span>
                                </span>
                            @endif
                        </a>
                        <a href="{{ route('orders.index') }}" @class(['hover:text-indigo-600', 'text-indigo-600' => request()->routeIs('orders.*', 'payments.*')])>My orders</a>
                    @endif
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

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white py-4 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</body>
</html>
