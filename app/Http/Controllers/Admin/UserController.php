<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * List the users, newest first, optionally filtered by search term and role.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ]);

        $search = $filters['search'] ?? null;
        $role = $filters['role'] ?? null;

        $users = User::query()
            ->withCount('orders')
            ->when($search !== null, fn (Builder $query) => $query->search($search))
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    /**
     * Show a user with what they have spent and the orders they placed, newest first.
     */
    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->loadCount('orders'),
            'totalSpent' => $user->orders()->paid()->sum('total_amount'),
            'orders' => $user->orders()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
