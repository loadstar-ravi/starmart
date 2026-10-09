<x-layouts.admin :title="$user->name">
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.users.index') }}" class="hover:text-indigo-600">Users</a>
        <span aria-hidden="true">/</span>
        <span class="text-slate-900" aria-current="page">{{ $user->name }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $user->email }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $user->role->label() }}</span>
                <x-admin.user-status-badge :user="$user" />
                @if ($user->isBlocked())
                    <span class="text-slate-600">Blocked on {{ $user->blocked_at->format('j M Y, g:i A') }}</span>
                @endif
            </div>
        </div>

        @can('block', $user)
            <form method="POST" action="{{ route('admin.users.status.update', $user) }}" @unless ($user->isBlocked()) onsubmit="return confirm('Block this customer? They will no longer be able to log in.')" @endunless>
                @csrf
                @method('PATCH')
                <input type="hidden" name="blocked" value="{{ $user->isBlocked() ? 0 : 1 }}">

                @if ($user->isBlocked())
                    <button type="submit" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50">Unblock customer</button>
                @else
                    <button type="submit" class="cursor-pointer rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Block customer</button>
                @endif
            </form>
        @else
            <p class="text-sm text-slate-600">Admin accounts cannot be blocked.</p>
        @endcan
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="Summary">
        <x-admin.stat-tile label="Registered on">{{ $user->created_at->format('j M Y') }}</x-admin.stat-tile>
        <x-admin.stat-tile label="Orders">{{ number_format($user->orders_count) }}</x-admin.stat-tile>
        <x-admin.stat-tile label="Total spent" hint="On paid orders"><x-money :amount="$totalSpent" /></x-admin.stat-tile>
    </section>

    <section class="mt-6 overflow-x-auto rounded-xl bg-white shadow-xs" aria-labelledby="orders-heading">
        <h2 id="orders-heading" class="px-4 pt-4 text-lg font-bold">Orders</h2>

        <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">Order</th>
                    <th scope="col" class="px-4 py-3">Placed on</th>
                    <th scope="col" class="px-4 py-3">Amount</th>
                    <th scope="col" class="px-4 py-3">Payment</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3 font-medium whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $order->created_at->format('j M Y, g:i A') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$order->total_amount" /></td>
                        <td class="px-4 py-3"><x-admin.payment-status-badge :status="$order->payment_status" /></td>
                        <td class="px-4 py-3"><x-admin.order-status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">This user has not placed any orders.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="mt-6">
        {{ $orders->links() }}
    </div>
</x-layouts.admin>
