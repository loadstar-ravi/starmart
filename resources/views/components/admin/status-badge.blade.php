@props(['active'])

<span @class([
    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
    'bg-green-100 text-green-800' => $active,
    'bg-slate-200 text-slate-600' => ! $active,
])>{{ $active ? 'Active' : 'Inactive' }}</span>
