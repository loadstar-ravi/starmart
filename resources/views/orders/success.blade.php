<x-layouts.app title="Order placed">
    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <section class="rounded-2xl bg-white px-6 py-10 text-center shadow-xs">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100 text-green-700" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>

            <h1 class="mt-4 text-2xl font-bold">Thank you! Your order is placed.</h1>
            <p class="mt-2 text-slate-600">Order number <span class="font-semibold text-slate-900">{{ $order->order_number }}</span></p>
            <p class="mt-1 text-sm text-slate-600">A confirmation email is on its way to you.</p>
        </section>

        @if ($order->isAwaitingPayment())
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-6" aria-labelledby="payment-needed-heading">
                <div>
                    <h2 id="payment-needed-heading" class="font-bold text-amber-900">Payment needed</h2>
                    <p class="mt-1 text-sm text-amber-800">This order is not paid yet. Pay online to complete it.</p>
                </div>
                <a href="{{ route('payments.create', $order->order_number) }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Pay now</a>
            </section>
        @endif

        <section class="rounded-2xl bg-white p-6 shadow-xs" aria-labelledby="items-heading">
            <h2 id="items-heading" class="text-lg font-bold">Items</h2>

            <ul class="mt-4 flex flex-col gap-3 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $item->product_name }}</span>
                            <span class="text-slate-500">&times; {{ $item->quantity }} at <x-money :amount="$item->unit_price" /></span>
                        </span>
                        <x-money :amount="$item->line_total" class="shrink-0" />
                    </li>
                @endforeach
            </ul>

            <dl class="mt-4 flex justify-between gap-4 border-t border-slate-200 pt-4">
                <dt class="font-semibold">Total</dt>
                <dd class="font-bold"><x-money :amount="$order->total_amount" /></dd>
            </dl>
        </section>

        <div class="grid gap-6 sm:grid-cols-2">
            <section class="rounded-2xl bg-white p-6 shadow-xs" aria-labelledby="delivery-heading">
                <h2 id="delivery-heading" class="text-lg font-bold">Delivery to</h2>

                <address class="mt-4 text-sm text-slate-700 not-italic">
                    <span class="font-medium text-slate-900">{{ $order->shipping_address['name'] }}</span><br>
                    <span class="whitespace-pre-line">{{ $order->shipping_address['address'] }}</span><br>
                    {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['pincode'] }}<br>
                    {{ $order->shipping_address['mobile'] }}<br>
                    {{ $order->shipping_address['email'] }}
                </address>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-xs" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-lg font-bold">Payment and status</h2>

                <dl class="mt-4 flex flex-col gap-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600">Payment method</dt>
                        <dd class="font-medium">{{ $order->payment_method->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600">Payment status</dt>
                        <dd class="font-medium">{{ $order->payment_status->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600">Order status</dt>
                        <dd class="font-medium">{{ $order->status->label() }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <a href="{{ route('products.index') }}" class="self-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Continue shopping</a>
    </div>
</x-layouts.app>
