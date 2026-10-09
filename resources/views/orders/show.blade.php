<x-layouts.app :title="'Order '.$order->order_number">
    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <a href="{{ route('orders.index') }}" class="hover:text-indigo-600">My orders</a>
            <span aria-hidden="true">/</span>
            <span class="text-slate-900" aria-current="page">{{ $order->order_number }}</span>
        </nav>

        <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-6 shadow-xs">
            <div>
                <h1 class="text-2xl font-bold">Order {{ $order->order_number }}</h1>
                <p class="mt-1 text-sm text-slate-600">Placed on {{ $order->created_at->format('j M Y, g:i A') }}</p>
            </div>

            @if ($order->status->isCancellable())
                <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}" data-submit-once onsubmit="return confirm('Cancel this order?')">
                    @csrf
                    <button type="submit" data-busy-label="Cancelling…" class="cursor-pointer rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60">Cancel order</button>
                </form>
            @endif
        </section>

        @include('orders._details', ['order' => $order])
    </div>
</x-layouts.app>
