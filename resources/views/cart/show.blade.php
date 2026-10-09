<x-layouts.app title="Your cart">
    <h1 class="text-2xl font-bold">Your cart</h1>

    @error('quantity')
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ $message }}
        </div>
    @enderror

    <div data-cart-errors hidden role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>

    <div class="relative mt-6">
        <div data-cart-loading hidden role="status" class="pointer-events-none absolute inset-x-0 top-12 z-10 flex justify-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-md">
                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                </svg>
                Updating your cart&hellip;
            </span>
        </div>

        <div data-cart-contents class="transition-opacity aria-busy:pointer-events-none aria-busy:opacity-40">
            @include('cart._contents', ['cart' => $cart])
        </div>
    </div>
</x-layouts.app>
