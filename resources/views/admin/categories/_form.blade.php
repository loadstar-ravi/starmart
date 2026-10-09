@csrf

<x-form.input name="name" label="Name" :value="$category->name" required maxlength="100" />
<x-form.input name="slug" label="Slug" :value="$category->slug" maxlength="120" hint="Leave blank to generate it from the name." />
<x-form.textarea name="description" label="Description" :value="$category->description" maxlength="1000" />

<x-form.select name="is_active" label="Status" required>
    <option value="1" @selected(old('is_active', $category->is_active ?? true))>Active</option>
    <option value="0" @selected(! old('is_active', $category->is_active ?? true))>Inactive</option>
</x-form.select>
