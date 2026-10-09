<x-layouts.admin title="Categories">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold">Categories</h1>
        <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New category</a>
    </div>

    <form method="GET" action="{{ route('admin.categories.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <x-form.input name="search" label="Search" type="search" :value="$search" placeholder="Category name" />
        </div>
        <x-form.button>Search</x-form.button>
        @if ($search !== null)
            <a href="{{ route('admin.categories.index') }}" class="py-2 text-sm font-medium text-slate-600 hover:underline">Clear</a>
        @endif
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">Name</th>
                    <th scope="col" class="px-4 py-3">Slug</th>
                    <th scope="col" class="px-4 py-3">Products</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $category->slug }}</td>
                        <td class="px-4 py-3">{{ $category->products_count }}</td>
                        <td class="px-4 py-3"><x-admin.status-badge :active="$category->is_active" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="font-medium text-indigo-600 hover:underline">Edit</a>

                                <form method="POST" action="{{ route('admin.categories.status.update', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                    <button type="submit" class="cursor-pointer font-medium text-slate-600 hover:underline">
                                        {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cursor-pointer font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">No categories found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $categories->links() }}
    </div>
</x-layouts.admin>
