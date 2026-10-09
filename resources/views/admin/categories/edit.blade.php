<x-layouts.admin title="Edit category">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold">Edit category</h1>

        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-6 flex flex-col gap-4 rounded-xl bg-white p-6 shadow-xs">
            @method('PUT')
            @include('admin.categories._form', ['category' => $category])

            <div class="flex items-center gap-4">
                <x-form.button>Save changes</x-form.button>
                <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-slate-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
