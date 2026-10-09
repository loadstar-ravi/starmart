<x-layouts.admin title="Orders">
    <h1 class="text-2xl font-bold">Orders</h1>

    <form method="GET" action="{{ route('admin.orders.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <x-form.input name="search" label="Search" type="search" :value="$filters['search']" placeholder="Order number, customer name or email" />
        </div>
        <div class="w-full sm:w-44">
            <x-form.select name="status" label="Order status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div class="w-full sm:w-44">
            <x-form.select name="payment_status" label="Payment status">
                <option value="">All payments</option>
                @foreach ($paymentStatuses as $paymentStatus)
                    <option value="{{ $paymentStatus->value }}" @selected($filters['payment_status'] === $paymentStatus->value)>{{ $paymentStatus->label() }}</option>
                @endforeach
            </x-form.select>
        </div>
        <x-form.button>Filter</x-form.button>
        @if (array_filter($filters, fn ($value) => $value !== null))
            <a href="{{ route('admin.orders.index') }}" class="py-2 text-sm font-medium text-slate-600 hover:underline">Clear</a>
        @endif
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">Order</th>
                    <th scope="col" class="px-4 py-3">Customer</th>
                    <th scope="col" class="px-4 py-3">Placed on</th>
                    <th scope="col" class="px-4 py-3">Amount</th>
                    <th scope="col" class="px-4 py-3">Payment</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3 font-medium whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order) }}" class="hover:text-indigo-600">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $order->user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $order->user->email }}</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $order->created_at->format('j M Y, g:i A') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$order->total_amount" /></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1">
                                <x-admin.payment-status-badge :status="$order->payment_status" />
                                <span class="text-xs whitespace-nowrap text-slate-500">{{ $order->payment_method->label() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3"><x-admin.order-status-badge :status="$order->status" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline" aria-label="View order {{ $order->order_number }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $orders->links() }}
    </div>
</x-layouts.admin>
