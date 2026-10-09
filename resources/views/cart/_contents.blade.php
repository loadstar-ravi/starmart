@if ($cart->items->isEmpty())
    <div class="rounded-2xl bg-white px-6 py-16 text-center text-slate-600 shadow-xs">
        <p class="font-medium text-slate-900">Your cart is empty.</p>
        <p class="mt-1 text-sm">Add a product and it will show up here.</p>
        <a href="{{ route('products.index') }}" class="mt-6 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Shop all products</a>
    </div>
@else
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <ul class="flex min-w-0 grow flex-col gap-4">
            @foreach ($cart->items as $item)
                @php($product = $item->product)

                <li class="flex gap-4 rounded-2xl bg-white p-4 shadow-xs">
                    <div class="size-20 shrink-0 overflow-hidden rounded-lg sm:size-24">
                        @if ($product->primaryImage)
                            <img src="{{ $product->primaryImage->url }}" alt="" loading="lazy" class="size-full object-cover">
                        @else
                            <x-product-placeholder />
                        @endif
                    </div>

                    <div class="flex min-w-0 grow flex-col gap-2">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                            <h2 class="font-semibold">
                                @if ($product->isVisibleToCustomers())
                                    <a href="{{ route('products.show', $product->slug) }}" class="hover:text-indigo-600">{{ $product->name }}</a>
                                @else
                                    {{ $product->name }}
                                @endif
                            </h2>

                            @if ($item->isPurchasable())
                                <p class="font-bold"><x-money :amount="$item->lineTotal()" /></p>
                            @endif
                        </div>

                        <p class="text-sm text-slate-500"><x-money :amount="$product->price" /> each</p>

                        @if (! $product->isVisibleToCustomers())
                            <p class="text-sm font-medium text-red-700">No longer available</p>
                        @elseif (! $product->isInStock())
                            <p class="text-sm font-medium text-red-700">Out of stock</p>
                        @elseif (! $item->isPurchasable())
                            <p class="text-sm font-medium text-amber-800">Only {{ $product->stock }} left. Lower the quantity to buy this.</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-4">
                            @if ($product->isVisibleToCustomers() && $product->isInStock())
                                <div class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('cart.items.update', $item) }}" data-cart-quantity="{{ $item->id }}-decrease">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ min($item->quantity - 1, $product->stock) }}">
                                        <button type="submit" aria-label="Decrease quantity of {{ $product->name }}" @disabled($item->quantity <= 1) class="size-8 cursor-pointer rounded-lg border border-slate-300 font-semibold hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">&minus;</button>
                                    </form>

                                    <form method="POST" action="{{ route('cart.items.update', $item) }}" data-cart-quantity="{{ $item->id }}-set" class="flex items-center gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <label for="quantity-{{ $item->id }}" class="sr-only">Quantity of {{ $product->name }}</label>
                                        <input
                                            id="quantity-{{ $item->id }}"
                                            name="quantity"
                                            type="number"
                                            min="1"
                                            max="{{ $product->stock }}"
                                            value="{{ $item->quantity }}"
                                            required
                                            class="w-16 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center text-sm text-slate-900 shadow-xs outline-none focus:ring-2 focus:ring-indigo-500"
                                        >
                                        <noscript>
                                            <button type="submit" class="cursor-pointer rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Update</button>
                                        </noscript>
                                    </form>

                                    <form method="POST" action="{{ route('cart.items.update', $item) }}" data-cart-quantity="{{ $item->id }}-increase">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $item->quantity + 1 }}">
                                        <button type="submit" aria-label="Increase quantity of {{ $product->name }}" class="size-8 cursor-pointer rounded-lg border border-slate-300 font-semibold hover:bg-slate-50">+</button>
                                    </form>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cursor-pointer text-sm font-medium text-red-600 hover:underline" aria-label="Remove {{ $product->name }} from your cart">Remove</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <aside class="rounded-2xl bg-white p-6 shadow-xs lg:w-80 lg:shrink-0" aria-labelledby="cart-summary-heading">
            <h2 id="cart-summary-heading" class="text-lg font-bold">Summary</h2>

            <dl class="mt-4 flex flex-col gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-600">Items</dt>
                    <dd class="font-medium">{{ $cart->totalQuantity() }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
                    <dt class="font-semibold">Total</dt>
                    <dd class="font-bold"><x-money :amount="$cart->total()" /></dd>
                </div>
            </dl>

            @if ($cart->hasUnpurchasableItems())
                <p class="mt-3 text-sm text-amber-800">Items that cannot be bought right now are left out of the total. Update or remove them to check out.</p>
            @else
                <a href="{{ route('checkout.create') }}" class="mt-6 block rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Proceed to checkout</a>
            @endif

            <a href="{{ route('products.index') }}" class="mt-4 block text-center text-sm font-medium text-indigo-600 hover:underline">Continue shopping</a>
        </aside>
    </div>
@endif
