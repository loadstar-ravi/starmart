<x-layouts.app :title="$product->name">
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('products.index') }}" class="hover:text-indigo-600">Products</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="hover:text-indigo-600">{{ $product->category->name }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-slate-900" aria-current="page">{{ $product->name }}</span>
    </nav>

    <div class="mt-6 grid gap-8 md:grid-cols-2">
        <div data-product-gallery>
            <div class="aspect-square overflow-hidden rounded-2xl bg-white shadow-xs">
                @if ($mainImage)
                    <img data-gallery-main src="{{ $mainImage->url }}" alt="{{ $product->name }}" class="size-full object-cover">
                @else
                    <x-product-placeholder />
                @endif
            </div>

            @if ($product->images->count() > 1)
                <ul class="mt-3 grid grid-cols-5 gap-2">
                    @foreach ($product->images as $image)
                        <li>
                            <button
                                type="button"
                                data-gallery-thumb="{{ $image->url }}"
                                aria-label="Show image {{ $loop->iteration }} of {{ $product->name }}"
                                aria-current="{{ $image->is($mainImage) ? 'true' : 'false' }}"
                                class="block aspect-square w-full cursor-pointer overflow-hidden rounded-lg ring-indigo-500 ring-offset-2 aria-[current=true]:ring-2"
                            >
                                <img src="{{ $image->url }}" alt="" loading="lazy" class="size-full object-cover">
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <p class="text-sm font-medium text-indigo-600">{{ $product->category->name }}</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $product->name }}</h1>
            <p class="mt-4 text-3xl font-bold"><x-money :amount="$product->price" /></p>

            @if (! $product->isInStock())
                <p class="mt-3 inline-flex rounded-full bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700">Out of stock</p>
            @elseif ($product->stock <= 5)
                <p class="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">Only {{ $product->stock }} left</p>
            @else
                <p class="mt-3 inline-flex rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-800">In stock</p>
            @endif

            @if ($product->description)
                <h2 class="mt-8 text-sm font-semibold tracking-wide text-slate-500 uppercase">Description</h2>
                <p class="mt-2 whitespace-pre-line text-slate-700">{{ $product->description }}</p>
            @endif
        </div>
    </div>
</x-layouts.app>
