<x-layouts.admin title="Dashboard">
    <h1 class="text-2xl font-bold">Dashboard</h1>

    <section class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Shop totals">
        <x-admin.stat-tile label="Total sales" hint="From paid orders"><x-money :amount="$overview['sales']" /></x-admin.stat-tile>
        <x-admin.stat-tile label="Orders" :href="route('admin.orders.index')">{{ number_format($overview['orders']) }}</x-admin.stat-tile>
        <x-admin.stat-tile label="Products" :href="route('admin.products.index')">{{ number_format($overview['products']) }}</x-admin.stat-tile>
        <x-admin.stat-tile label="Customers" :href="route('admin.users.index', ['role' => 'customer'])">{{ number_format($overview['customers']) }}</x-admin.stat-tile>
    </section>

    <section class="mt-8" aria-labelledby="orders-by-payment-heading">
        <h2 id="orders-by-payment-heading" class="text-lg font-bold">Orders by payment status</h2>

        <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($paymentStatuses as $paymentStatus)
                <x-admin.stat-tile :label="$paymentStatus->label()" :href="route('admin.orders.index', ['payment_status' => $paymentStatus->value])">
                    {{ number_format($overview['orders_by_payment_status'][$paymentStatus->value]) }}
                </x-admin.stat-tile>
            @endforeach
        </div>
    </section>

    <section class="mt-8 rounded-xl bg-white p-4 shadow-xs sm:p-6" aria-labelledby="recent-sales-heading">
        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
            <div>
                <h2 id="recent-sales-heading" class="text-lg font-bold">Sales in the last 30 days</h2>
                <p class="mt-1 text-sm text-slate-600">
                    <x-money :amount="$recentTotals['sales']" class="font-semibold text-slate-900" />
                    from {{ number_format($recentTotals['orders']) }} paid {{ Str::plural('order', $recentTotals['orders']) }}
                </p>
            </div>
            <a href="{{ route('admin.reports.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">View sales report</a>
        </div>

        <x-admin.sales-chart :days="$dailySales" label="Sales per day in the last 30 days" class="mt-4" />

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-medium text-indigo-600 hover:underline">Show the daily figures as a table</summary>

            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                <x-admin.daily-sales-table :days="$dailySales" :totals="$recentTotals" />
            </div>
        </details>
    </section>
</x-layouts.admin>
