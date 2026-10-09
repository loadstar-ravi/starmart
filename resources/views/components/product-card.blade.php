@props(['product'])

<article class="flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-xs transition-shadow hover:shadow-md">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-square overflow-hidden" tabindex="-1" aria-hidden="true">
        @if ($product->primaryImage)
            <img src="{{ $product->primaryImage->url }}" alt="" loading="lazy" class="size-full object-cover">
        @else
            <x-product-placeholder />
        @endif

        @unless ($product->isInStock())
            <span class="absolute top-2 left-2 rounded-full bg-slate-900/80 px-2 py-0.5 text-xs font-semibold text-white">Out of stock</span>
        @endunless
    </a>

    <div class="flex grow flex-col gap-1 p-3">
        <p class="text-xs text-slate-500">{{ $product->category->name }}</p>
        <h3 class="text-sm font-semibold">
            <a href="{{ route('products.show', $product->slug) }}" class="hover:text-indigo-600">{{ $product->name }}</a>
        </h3>
        <p class="mt-auto pt-2 font-bold"><x-money :amount="$product->price" /></p>
        @unless ($product->isInStock())
            <p class="sr-only">Out of stock</p>
        @endunless

        @if ($product->isInStock() && auth()->user()?->isCustomer())
            <form method="POST" action="{{ route('cart.items.store') }}" class="pt-2">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="w-full cursor-pointer rounded-lg border border-indigo-600 px-3 py-1.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50" aria-label="Add {{ $product->name }} to your cart">Add to cart</button>
            </form>
        @endif
    </div>
</article>
