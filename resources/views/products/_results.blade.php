<p class="text-sm text-slate-600">
    @if ($products->isNotEmpty())
        Showing {{ $products->firstItem() }}&ndash;{{ $products->lastItem() }} of {{ $products->total() }} {{ Str::plural('product', $products->total()) }}
    @else
        No products found
    @endif
</p>

@if ($products->isEmpty())
    <div class="mt-4 rounded-2xl bg-white px-6 py-16 text-center text-slate-600 shadow-xs">
        <p class="font-medium text-slate-900">No products match your filters.</p>
        <p class="mt-1 text-sm">Try a different search, or clear the filters.</p>
    </div>
@else
    <ul class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3">
        @foreach ($products as $product)
            <li><x-product-card :product="$product" /></li>
        @endforeach
    </ul>

    <div class="mt-6" data-product-pagination>
        {{ $products->links() }}
    </div>
@endif
