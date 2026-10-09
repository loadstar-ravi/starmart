@use('App\Enums\OrderStatus')

<x-layouts.admin :title="'Order '.$order->order_number">
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.orders.index') }}" class="hover:text-indigo-600">Orders</a>
        <span aria-hidden="true">/</span>
        <span class="text-slate-900" aria-current="page">{{ $order->order_number }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Order {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-slate-600">Placed on {{ $order->created_at->format('j M Y, g:i A') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-admin.order-status-badge :status="$order->status" />
            <x-admin.payment-status-badge :status="$order->payment_status" />
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex min-w-0 grow flex-col gap-6">
            <section class="overflow-x-auto rounded-xl bg-white shadow-xs" aria-labelledby="items-heading">
                <h2 id="items-heading" class="px-4 pt-4 text-lg font-bold">Items</h2>

                <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">Product</th>
                            <th scope="col" class="px-4 py-3 text-right">Price</th>
                            <th scope="col" class="px-4 py-3 text-right">Quantity</th>
                            <th scope="col" class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->product_name }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->unit_price" /></td>
                                <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->line_total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200">
                        <tr>
                            <th scope="row" colspan="3" class="px-4 py-3 text-right font-semibold">Order total</th>
                            <td class="px-4 py-3 text-right font-bold whitespace-nowrap"><x-money :amount="$order->total_amount" /></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="overflow-x-auto rounded-xl bg-white shadow-xs" aria-labelledby="payments-heading">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4">
                    <h2 id="payments-heading" class="text-lg font-bold">Payment attempts</h2>
                    <p class="text-sm text-slate-600">{{ $order->payment_method->label() }}</p>
                </div>

                <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">Started</th>
                            <th scope="col" class="px-4 py-3">Amount</th>
                            <th scope="col" class="px-4 py-3">Result</th>
                            <th scope="col" class="px-4 py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($order->payments as $payment)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $payment->created_at->format('j M Y, g:i A') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$payment->amount" /></td>
                                <td class="px-4 py-3"><x-admin.payment-status-badge :status="$payment->status" /></td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($payment->transaction_reference)
                                        Reference {{ $payment->transaction_reference }}
                                    @elseif ($payment->failure_reason)
                                        {{ $payment->failure_reason }}
                                    @elseif ($payment->paid_at)
                                        Paid on {{ $payment->paid_at->format('j M Y, g:i A') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">No payment attempts recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="flex flex-col gap-6 lg:w-80 lg:shrink-0">
            <section class="rounded-xl bg-white p-4 shadow-xs" aria-labelledby="update-status-heading">
                <h2 id="update-status-heading" class="text-lg font-bold">Update status</h2>

                @if ($order->status->laterSteps() !== [])
                    <form method="POST" action="{{ route('admin.orders.status.update', $order) }}" class="mt-4 flex flex-col gap-3">
                        @csrf
                        @method('PATCH')
                        <x-form.select name="status" label="Move order to" required>
                            @foreach ($order->status->laterSteps() as $step)
                                <option value="{{ $step->value }}">{{ $step->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.button>Update status</x-form.button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-slate-600">This order is {{ Str::lower($order->status->label()) }}. Its status can no longer be changed.</p>
                @endif

                @if ($order->status->isCancellable())
                    <form method="POST" action="{{ route('admin.orders.status.update', $order) }}" class="mt-4 border-t border-slate-200 pt-4" onsubmit="return confirm('Cancel this order? Its stock is put back and a paid order is refunded.')">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ OrderStatus::Cancelled->value }}">
                        <button type="submit" class="w-full cursor-pointer rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Cancel order</button>
                    </form>
                @elseif ($order->status->laterSteps() !== [])
                    <p class="mt-4 border-t border-slate-200 pt-4 text-sm text-slate-600">This order is past the point where it can be cancelled.</p>
                @endif
            </section>

            <section class="rounded-xl bg-white p-4 shadow-xs" aria-labelledby="customer-heading">
                <h2 id="customer-heading" class="text-lg font-bold">Customer</h2>

                <p class="mt-2 text-sm font-medium">{{ $order->user->name }}</p>
                <p class="text-sm text-slate-600">{{ $order->user->email }}</p>
            </section>

            <section class="rounded-xl bg-white p-4 shadow-xs" aria-labelledby="delivery-heading">
                <h2 id="delivery-heading" class="text-lg font-bold">Delivery to</h2>

                <address class="mt-2 text-sm text-slate-700 not-italic">
                    <span class="font-medium text-slate-900">{{ $order->shipping_address['name'] }}</span><br>
                    <span class="whitespace-pre-line">{{ $order->shipping_address['address'] }}</span><br>
                    {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['pincode'] }}<br>
                    {{ $order->shipping_address['mobile'] }}<br>
                    {{ $order->shipping_address['email'] }}
                </address>
            </section>
        </div>
    </div>
</x-layouts.admin>
