<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
]) }}>
    {{ $slot }}
</button>
