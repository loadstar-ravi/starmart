<x-layouts.app>
    <section class="rounded-2xl bg-white px-6 py-16 text-center shadow-xs">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Welcome to {{ config('app.name') }}</h1>
        <p class="mx-auto mt-4 max-w-xl text-slate-600">
            Everything you need, delivered to your door.
        </p>

        @guest
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">Login</a>
            </div>
        @endguest
    </section>
</x-layouts.app>
