@props(['name', 'label'])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="text-sm font-medium text-slate-700">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-xs outline-none focus:ring-2 focus:ring-indigo-500',
            'border-red-500' => $errors->has($name),
            'border-slate-300' => ! $errors->has($name),
        ]) }}
    >
        {{ $slot }}
    </select>
    @error($name)
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
