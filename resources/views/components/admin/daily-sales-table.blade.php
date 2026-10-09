@props(['days', 'totals'])

<table {{ $attributes->class(['min-w-full divide-y divide-slate-200 text-sm']) }}>
    <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
        <tr>
            <th scope="col" class="px-4 py-3">Date</th>
            <th scope="col" class="px-4 py-3 text-right">Paid orders</th>
            <th scope="col" class="px-4 py-3 text-right">Sales</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @foreach ($days as $day)
            <tr>
                <td class="px-4 py-2 whitespace-nowrap">{{ $day['date']->format('j M Y') }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($day['orders']) }}</td>
                <td class="px-4 py-2 text-right whitespace-nowrap tabular-nums"><x-money :amount="$day['sales']" /></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot class="border-t border-slate-200 font-semibold">
        <tr>
            <th scope="row" class="px-4 py-3 text-left">Total</th>
            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['orders']) }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums"><x-money :amount="$totals['sales']" /></td>
        </tr>
    </tfoot>
</table>
