@use('App\Enums\ProductStatus')

<x-layouts.admin title="Products">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Products</h1>
        <a href="{{ route('admin.products.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New product</a>
    </div>

    <form method="GET" action="{{ route('admin.products.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-64">
            <x-form.input name="search" label="Search" type="search" :value="$filters['search']" placeholder="Product name" />
        </div>
        <div class="w-full sm:w-56">
            <x-form.select name="category" label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['category'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-form.select>
        </div>
        <div class="w-full sm:w-44">
            <x-form.select name="status" label="Status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form.select>
        </div>
        <x-form.button>Filter</x-form.button>
        @if (array_filter($filters, fn ($value) => $value !== null))
            <a href="{{ route('admin.products.index') }}" class="py-2 text-sm font-medium text-slate-600 hover:underline">Clear</a>
        @endif
    </form>

    @error('stock')
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">Product</th>
                    <th scope="col" class="px-4 py-3">Category</th>
                    <th scope="col" class="px-4 py-3">Price</th>
                    <th scope="col" class="px-4 py-3">Stock</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex min-w-56 items-center gap-3">
                                @if ($product->primaryImage)
                                    <img src="{{ $product->primaryImage->url }}" alt="" class="size-12 shrink-0 rounded-lg object-cover">
                                @else
                                    <div class="size-12 shrink-0 rounded-lg bg-slate-100" aria-hidden="true"></div>
                                @endif
                                <div>
                                    <p class="font-medium">{{ $product->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $product->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $product->category->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$product->price" /></td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.products.stock.update', $product) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label for="stock-{{ $product->id }}" class="sr-only">Stock for {{ $product->name }}</label>
                                <input id="stock-{{ $product->id }}" name="stock" type="number" min="0" step="1" value="{{ $product->stock }}" required class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <button type="submit" class="cursor-pointer font-medium text-indigo-600 hover:underline">Save</button>
                            </form>
                            @if ($product->stock === 0)
                                <p class="mt-1 text-xs font-medium text-red-600">Out of stock</p>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-admin.status-badge :active="$product->isActive()" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                <a href="{{ route('admin.products.edit', $product) }}" class="font-medium text-indigo-600 hover:underline">Edit</a>

                                <form method="POST" action="{{ route('admin.products.status.update', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $product->isActive() ? ProductStatus::Inactive->value : ProductStatus::Active->value }}">
                                    <button type="submit" class="cursor-pointer font-medium text-slate-600 hover:underline">
                                        {{ $product->isActive() ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cursor-pointer font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>
</x-layouts.admin>
