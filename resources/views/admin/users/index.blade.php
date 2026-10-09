<x-layouts.admin title="Users">
    <h1 class="text-2xl font-bold">Users</h1>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <x-form.input name="search" label="Search" type="search" :value="$filters['search']" placeholder="Name or email" />
        </div>
        <div class="w-full sm:w-44">
            <x-form.select name="role" label="Role">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </x-form.select>
        </div>
        <x-form.button>Filter</x-form.button>
        @if (array_filter($filters, fn ($value) => $value !== null))
            <a href="{{ route('admin.users.index') }}" class="py-2 text-sm font-medium text-slate-600 hover:underline">Clear</a>
        @endif
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">User</th>
                    <th scope="col" class="px-4 py-3">Role</th>
                    <th scope="col" class="px-4 py-3">Registered on</th>
                    <th scope="col" class="px-4 py-3 text-right">Orders</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-medium hover:text-indigo-600">{{ $user->name }}</a>
                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $user->created_at->format('j M Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($user->orders_count) }}</td>
                        <td class="px-4 py-3"><x-admin.user-status-badge :user="$user" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-indigo-600 hover:underline" aria-label="View {{ $user->name }}">View</a>

                                @can('block', $user)
                                    <form method="POST" action="{{ route('admin.users.status.update', $user) }}" @unless ($user->isBlocked()) onsubmit="return confirm('Block this customer? They will no longer be able to log in.')" @endunless>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="blocked" value="{{ $user->isBlocked() ? 0 : 1 }}">
                                        <button type="submit" @class(['cursor-pointer font-medium hover:underline', 'text-red-600' => ! $user->isBlocked(), 'text-slate-600' => $user->isBlocked()]) aria-label="{{ $user->isBlocked() ? 'Unblock' : 'Block' }} {{ $user->name }}">
                                            {{ $user->isBlocked() ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</x-layouts.admin>
