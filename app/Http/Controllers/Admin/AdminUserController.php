<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'role']);

        return Inertia::render('Admin/Users/Index', [
            'filters' => $filters,
            'users' => User::query()
                ->with('currentAccount:id,name')
                ->withCount('accounts')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('global_role', $role))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return Inertia::render('Admin/Users/Edit', [
            'managedUser' => $user->load(['currentAccount:id,name', 'accounts:id,name']),
            'accounts' => Account::query()->orderBy('name')->get(['id', 'name']),
            'roles' => ['user', 'manager', 'super_admin'],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'global_role' => ['required', Rule::in(['user', 'manager', 'super_admin'])],
            'current_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated.');
    }
}
