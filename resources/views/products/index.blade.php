<x-layouts.app title="Products">
    <h1 class="text-2xl font-bold">Products</h1>

    <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-start">
        <form
            method="GET"
            action="{{ route('products.index') }}"
            data-product-filters
            aria-label="Filter products"
            class="grid grid-cols-2 gap-3 rounded-2xl bg-white p-4 shadow-xs lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:gap-4"
        >
            <div class="col-span-2">
                <x-form.input name="search" label="Search" type="search" :value="$filters['search'] ?? null" placeholder="Search products" maxlength="100" />
            </div>

            <x-form.select name="category" label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(old('category', $filters['category'] ?? null) === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </x-form.select>

            <x-form.select name="sort" label="Sort by">
                @foreach ($sorts as $sort)
                    <option value="{{ $sort->value }}" @selected(old('sort', $filters['sort'] ?? $sorts[0]->value) === $sort->value)>{{ $sort->label() }}</option>
                @endforeach
            </x-form.select>

            <x-form.input name="min_price" label="Min price (₹)" type="number" min="0" step="1" :value="$filters['min_price'] ?? null" />
            <x-form.input name="max_price" label="Max price (₹)" type="number" min="0" step="1" :value="$filters['max_price'] ?? null" />

            <div class="col-span-2 flex items-center gap-4">
                <x-form.button>Apply</x-form.button>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-slate-600 hover:underline">Clear filters</a>
            </div>
        </form>

        <section class="min-w-0 grow" aria-label="Products">
            <div data-product-errors hidden role="alert" class="mb-4 flex flex-col gap-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>

            <div class="relative">
                <div data-product-loading hidden role="status" class="pointer-events-none absolute inset-x-0 top-12 z-10 flex justify-center">
                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-md">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                        </svg>
                        Loading products&hellip;
                    </span>
                </div>

                <div data-product-results aria-live="polite" class="transition-opacity aria-busy:pointer-events-none aria-busy:opacity-40">
                    @include('products._results', ['products' => $products])
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
