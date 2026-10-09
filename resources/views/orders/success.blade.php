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

        @include('orders._details', ['order' => $order])

        <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('products.index') }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Continue shopping</a>
            <a href="{{ route('orders.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">View my orders</a>
        </div>
    </div>
</x-layouts.app>
