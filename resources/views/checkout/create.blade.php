<x-layouts.app title="Checkout">
    <h1 class="text-2xl font-bold">Checkout</h1>

    <form method="POST" action="{{ route('checkout.store') }}" data-submit-once class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-start">
        @csrf

        <div class="flex min-w-0 grow flex-col gap-6">
            <section class="rounded-2xl bg-white p-6 shadow-xs" aria-labelledby="delivery-heading">
                <h2 id="delivery-heading" class="text-lg font-bold">Delivery details</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-form.input name="name" label="Full name" :value="auth()->user()->name" required maxlength="255" autocomplete="name" />
                    <x-form.input name="mobile" label="Mobile number" type="tel" required inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel-national" hint="10 digits, without +91." />

                    <div class="sm:col-span-2">
                        <x-form.input name="email" label="Email" type="email" :value="auth()->user()->email" required maxlength="255" autocomplete="email" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-form.textarea name="address" label="Address" required maxlength="500" autocomplete="street-address" />
                    </div>

                    <x-form.input name="city" label="City" required maxlength="100" autocomplete="address-level2" />
                    <x-form.input name="state" label="State" required maxlength="100" autocomplete="address-level1" />
                    <x-form.input name="pincode" label="Pincode" required inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" autocomplete="postal-code" />
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-xs" aria-labelledby="payment-heading">
                <h2 id="payment-heading" class="text-lg font-bold">Payment method</h2>

                <fieldset class="mt-4 flex flex-col gap-3">
                    <legend class="sr-only">Payment method</legend>

                    @foreach ($paymentMethods as $paymentMethod)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 px-4 py-3 text-sm font-medium has-checked:border-indigo-600 has-checked:bg-indigo-50">
                            <input
                                type="radio"
                                name="payment_method"
                                value="{{ $paymentMethod->value }}"
                                @checked(old('payment_method', $paymentMethods[0]->value) === $paymentMethod->value)
                                required
                                class="accent-indigo-600"
                            >
                            {{ $paymentMethod->label() }}
                        </label>
                    @endforeach
                </fieldset>

                @error('payment_method')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </section>
        </div>

        <aside class="rounded-2xl bg-white p-6 shadow-xs lg:w-96 lg:shrink-0" aria-labelledby="order-summary-heading">
            <h2 id="order-summary-heading" class="text-lg font-bold">Order summary</h2>

            <ul class="mt-4 flex flex-col gap-3 text-sm">
                @foreach ($cart->items as $item)
                    <li class="flex justify-between gap-4">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $item->product->name }}</span>
                            <span class="text-slate-500">&times; {{ $item->quantity }}</span>
                        </span>
                        <x-money :amount="$item->lineTotal()" class="shrink-0" />
                    </li>
                @endforeach
            </ul>

            <dl class="mt-4 flex justify-between gap-4 border-t border-slate-200 pt-4">
                <dt class="font-semibold">Total</dt>
                <dd class="font-bold"><x-money :amount="$cart->total()" /></dd>
            </dl>

            <x-form.button data-busy-label="Placing order…" class="mt-6 w-full disabled:cursor-not-allowed disabled:opacity-60">Place order</x-form.button>

            <a href="{{ route('cart.show') }}" class="mt-3 block text-center text-sm font-medium text-indigo-600 hover:underline">Back to cart</a>
        </aside>
    </form>
</x-layouts.app>
