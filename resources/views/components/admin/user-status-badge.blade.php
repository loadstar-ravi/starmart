@props(['user'])

<span @class([
    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
    'bg-red-100 text-red-800' => $user->isBlocked(),
    'bg-green-100 text-green-800' => ! $user->isBlocked(),
])>{{ $user->isBlocked() ? 'Blocked' : 'Active' }}</span>
