{{-- Which way a tooltip opens, so the ones near an edge stay inside the chart. --}}
@php($sides = ['start' => 'left-0', 'center' => 'left-1/2 -translate-x-1/2', 'end' => 'right-0'])

<figure {{ $attributes }}>
    <figcaption class="sr-only">{{ $label }}, as a column chart. The same figures are in the table that goes with it.</figcaption>

    @if ($axisMax > 0)
        {{-- The value axis sits outside the scrolling part, so it stays in view when a long chart is scrolled. --}}
        <div class="flex">
            <div class="relative mt-16 h-48 w-14 shrink-0" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span class="absolute right-2 translate-y-1/2 text-xs whitespace-nowrap text-slate-500 tabular-nums" style="bottom: {{ $tick['position'] }}%">{{ $tick['label'] }}</span>
                @endforeach
            </div>

            <div class="min-w-0 grow overflow-x-auto" data-sales-chart-scroller>
                {{-- The space above the plot is where the tooltip of the tallest column opens. --}}
                <div class="pt-16 pr-4" style="min-width: {{ count($columns) * 0.5 }}rem">
                    <div class="relative h-48">
                        @foreach ($ticks as $tick)
                            <div @class(['absolute inset-x-0 border-t', 'border-slate-300' => $loop->first, 'border-slate-100' => ! $loop->first]) style="bottom: {{ $tick['position'] }}%"></div>
                        @endforeach

                        <ul role="list" class="absolute inset-0 flex items-end gap-0.5" data-sales-chart>
                            @foreach ($columns as $column)
                                @php($orders = $column['orders'].' paid '.Str::plural('order', $column['orders']))

                                <li
                                    class="group relative flex h-full flex-1 items-end justify-center rounded-t hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-500"
                                    tabindex="{{ $loop->last ? 0 : -1 }}"
                                    data-sales-chart-column
                                    aria-label="{{ $column['date']->format('j M Y') }}: ₹{{ number_format((float) $column['sales'], 2) }} from {{ $orders }}"
                                >
                                    {{-- The label and the tooltip hang off the bar itself, so they sit right above it however wide the day's slot is. --}}
                                    <div
                                        @class(['relative w-full max-w-6 rounded-t bg-indigo-600 group-hover:bg-indigo-500 group-focus-visible:bg-indigo-500', 'min-h-0.5' => $column['height'] > 0])
                                        style="height: {{ $column['height'] }}%"
                                    >
                                        @if ($column['isPeak'])
                                            <span class="pointer-events-none absolute bottom-full {{ $sides[$column['side']] }} mb-1 text-xs font-medium whitespace-nowrap text-slate-700 group-hover:hidden group-focus-visible:hidden" aria-hidden="true">&#8377;{{ number_format((float) $column['sales']) }}</span>
                                        @endif

                                        <div class="pointer-events-none absolute bottom-full {{ $sides[$column['side']] }} z-10 mb-2 hidden rounded-lg bg-slate-900 px-3 py-2 text-xs whitespace-nowrap text-white shadow-lg group-hover:block group-focus-visible:block" aria-hidden="true">
                                            <p class="text-slate-300">{{ $column['date']->format('j M Y') }}</p>
                                            <p><span class="text-sm font-semibold">&#8377;{{ number_format((float) $column['sales'], 2) }}</span> <span class="text-slate-300">&middot; {{ $orders }}</span></p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="relative mt-2 h-4" aria-hidden="true">
                        @foreach ($columns as $column)
                            @if ($column['showsDate'])
                                <span @class(['absolute -translate-x-1/2 text-xs whitespace-nowrap text-slate-500', 'hidden sm:block' => ! $column['showsDateWhenNarrow']]) style="left: {{ $column['center'] }}%">{{ $column['date']->format('j M') }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <p class="rounded-lg bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">No sales in this period.</p>
    @endif
</figure>
