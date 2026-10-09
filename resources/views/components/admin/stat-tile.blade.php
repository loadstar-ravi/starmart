@props(['label', 'href' => null, 'hint' => null])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['block rounded-xl bg-white p-4 shadow-xs hover:ring-2 hover:ring-indigo-200 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none']) }}>
        <span class="block text-sm text-slate-600">{{ $label }}</span>
        <span class="mt-1 block text-2xl font-semibold">{{ $slot }}</span>
        @if ($hint)
            <span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </a>
@else
    <div {{ $attributes->class(['rounded-xl bg-white p-4 shadow-xs']) }}>
        <p class="text-sm text-slate-600">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold">{{ $slot }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </div>
@endif
