@csrf

<x-form.input name="name" label="Name" :value="$product->name" required maxlength="150" />
<x-form.input name="slug" label="Slug" :value="$product->slug" maxlength="170" hint="Leave blank to generate it from the name." />

<x-form.select name="category_id" label="Category" required>
    <option value="">Select a category</option>
    @foreach ($categories as $category)
        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>
            {{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}
        </option>
    @endforeach
</x-form.select>

<x-form.textarea name="description" label="Description" :value="$product->description" maxlength="5000" />

<div class="grid gap-4 sm:grid-cols-3">
    <x-form.input name="price" label="Price (₹)" type="number" :value="$product->price" required min="0.01" step="0.01" />
    <x-form.input name="stock" label="Stock" type="number" :value="$product->stock" required min="0" step="1" />

    <x-form.select name="status" label="Status" required>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(old('status', $product->status->value) === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </x-form.select>
</div>

<div class="flex flex-col gap-1">
    <label for="images" class="text-sm font-medium text-slate-700">{{ $product->exists ? 'Add images' : 'Images' }}</label>
    <input
        id="images"
        name="images[]"
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp"
        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-sm file:font-medium"
    >
    <p class="text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB each, at most 5 per upload.</p>
    @error('images')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    @foreach ($errors->get('images.*') as $messages)
        @foreach ($messages as $message)
            <p class="text-sm text-red-600">{{ $message }}</p>
        @endforeach
    @endforeach
</div>
