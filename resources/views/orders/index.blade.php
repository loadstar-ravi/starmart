<x-layouts.app title="My orders">
    <h1 class="text-2xl font-bold">My orders</h1>

    @if ($orders->isEmpty())
        <div class="mt-6 rounded-2xl bg-white px-6 py-16 text-center text-slate-600 shadow-xs">
            <p class="font-medium text-slate-900">You have not placed any orders yet.</p>
            <p class="mt-1 text-sm">When you do, they will show up here.</p>
            <a href="{{ route('products.index') }}" class="mt-6 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Shop all products</a>
        </div>
    @else
        <ul class="mt-6 flex flex-col gap-4">
            @foreach ($orders as $order)
                @php($units = $order->items->sum('quantity'))

                <li class="rounded-2xl bg-white p-4 shadow-xs sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                        <div>
                            <h2 class="font-semibold">
                                <a href="{{ route('orders.show', $order->order_number) }}" class="hover:text-indigo-600">{{ $order->order_number }}</a>
                            </h2>
                            <p class="text-sm text-slate-500">{{ $order->created_at->format('j M Y') }} &middot; {{ $units }} {{ Str::plural('item', $units) }}</p>
                        </div>
                        <p class="font-bold"><x-money :amount="$order->total_amount" /></p>
                    </div>

                    <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                        <div class="flex gap-2">
                            <dt class="text-slate-600">Order status</dt>
                            <dd class="font-medium">{{ $order->status->label() }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-slate-600">Payment status</dt>
                            <dd class="font-medium">{{ $order->payment_status->label() }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-4 text-sm font-medium">
                        <a href="{{ route('orders.show', $order->order_number) }}" class="text-indigo-600 hover:underline" aria-label="View order {{ $order->order_number }}">View order</a>
                        @if ($order->isAwaitingPayment())
                            <a href="{{ route('payments.create', $order->order_number) }}" class="text-indigo-600 hover:underline" aria-label="Pay for order {{ $order->order_number }}">Pay now</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    @endif
</x-layouts.app>
