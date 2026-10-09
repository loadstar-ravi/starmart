<x-layouts.admin title="Edit product">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold">Edit product</h1>

        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-4 rounded-xl bg-white p-6 shadow-xs">
            @method('PUT')
            @include('admin.products._form', ['product' => $product, 'categories' => $categories, 'statuses' => $statuses])

            <div class="flex items-center gap-4">
                <x-form.button>Save changes</x-form.button>
                <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-slate-600 hover:underline">Cancel</a>
            </div>
        </form>

        <section class="mt-8 rounded-xl bg-white p-6 shadow-xs">
            <h2 class="text-lg font-semibold">Current images</h2>

            @if ($product->images->isEmpty())
                <p class="mt-2 text-sm text-slate-500">This product has no images yet.</p>
            @else
                <ul class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($product->images as $image)
                        <li class="flex flex-col gap-2">
                            <img src="{{ $image->url }}" alt="{{ $product->name }}" class="aspect-square w-full rounded-lg object-cover">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="text-xs font-semibold text-indigo-600">{{ $image->is_primary ? 'Primary' : '' }}</span>
                                <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Remove this image?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cursor-pointer font-medium text-red-600 hover:underline">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.admin>
