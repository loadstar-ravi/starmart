<x-layouts.admin title="Sales report">
    <h1 class="text-2xl font-bold">Sales report</h1>
    <p class="mt-1 text-sm text-slate-600">Sales count paid orders, on the day each order was placed. Unpaid, failed and refunded orders are left out.</p>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2" role="group" aria-label="Quick ranges">
            @foreach ($presets as $preset)
                <a
                    href="{{ $preset['url'] }}"
                    @class([
                        'rounded-lg border px-3 py-2 text-sm font-medium',
                        'border-indigo-600 bg-indigo-50 text-indigo-700' => $preset['active'],
                        'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' => ! $preset['active'],
                    ])
                    @if ($preset['active']) aria-current="true" @endif
                >{{ $preset['label'] }}</a>
            @endforeach
        </div>

        <div class="w-full sm:w-44">
            <x-form.input name="from" label="From" type="date" :value="$from->toDateString()" :max="now()->toDateString()" />
        </div>
        <div class="w-full sm:w-44">
            <x-form.input name="to" label="To" type="date" :value="$to->toDateString()" :max="now()->toDateString()" />
        </div>
        <x-form.button>Apply</x-form.button>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="Totals for {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}">
        <x-admin.stat-tile label="Sales"><x-money :amount="$totals['sales']" /></x-admin.stat-tile>
        <x-admin.stat-tile label="Paid orders">{{ number_format($totals['orders']) }}</x-admin.stat-tile>
        <x-admin.stat-tile label="Average order value"><x-money :amount="$totals['average_order']" /></x-admin.stat-tile>
    </section>

    <section class="mt-6 rounded-xl bg-white p-4 shadow-xs sm:p-6" aria-labelledby="sales-per-day-heading">
        <h2 id="sales-per-day-heading" class="text-lg font-bold">Sales per day</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</p>

        <x-admin.sales-chart :days="$dailySales" label="Sales per day from {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}" class="mt-4" />
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
        <section class="overflow-x-auto rounded-xl bg-white shadow-xs" aria-labelledby="best-sellers-heading">
            <h2 id="best-sellers-heading" class="px-4 pt-4 text-lg font-bold">Best-selling products</h2>

            <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="px-4 py-3 text-right">Units sold</th>
                        <th scope="col" class="px-4 py-3 text-right">Sales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($topProducts as $product)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $product['name'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($product['units']) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap tabular-nums"><x-money :amount="$product['sales']" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-slate-500">No products were sold in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="overflow-x-auto rounded-xl bg-white shadow-xs" aria-labelledby="daily-figures-heading">
            <h2 id="daily-figures-heading" class="px-4 pt-4 text-lg font-bold">Daily figures</h2>

            <x-admin.daily-sales-table :days="$dailySales" :totals="$totals" class="mt-3" />
        </section>
    </div>
</x-layouts.admin>
