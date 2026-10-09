<x-layouts.admin title="New category">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold">New category</h1>

        <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-6 flex flex-col gap-4 rounded-xl bg-white p-6 shadow-xs">
            @include('admin.categories._form', ['category' => $category])

            <div class="flex items-center gap-4">
                <x-form.button>Create category</x-form.button>
                <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-slate-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
