<x-layouts.app>
    <section class="rounded-2xl bg-white px-6 py-16 text-center shadow-xs">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Welcome to {{ config('app.name') }}</h1>
        <p class="mx-auto mt-4 max-w-xl text-slate-600">
            Everything you need, delivered to your door.
        </p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('products.index') }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Shop all products</a>
            @guest
                <a href="{{ route('register') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">Login</a>
            @endguest
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mt-10" aria-labelledby="categories-heading">
            <h2 id="categories-heading" class="text-xl font-bold">Shop by category</h2>
            <ul class="mt-4 flex flex-wrap gap-3">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('products.index', ['category' => $category->slug]) }}" class="inline-flex rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium shadow-xs hover:border-indigo-300 hover:text-indigo-600">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($newArrivals->isNotEmpty())
        <section class="mt-10" aria-labelledby="new-arrivals-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="new-arrivals-heading" class="text-xl font-bold">New arrivals</h2>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">View all products</a>
            </div>
            <ul class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($newArrivals as $product)
                    <li><x-product-card :product="$product" /></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
