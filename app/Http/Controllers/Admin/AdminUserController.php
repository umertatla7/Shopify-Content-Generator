<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Permission;
use App\Models\Role;
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
            'accountRoles' => Role::query()->orderBy('label')->get(['id', 'name', 'label']),
            'permissions' => Permission::query()->orderBy('label')->get(['id', 'name', 'label']),
            'memberships' => AccountUser::query()
                ->with(['account:id,name', 'role:id,name,label', 'role.permissions:id,name,label'])
                ->where('user_id', $user->id)
                ->orderBy('account_id')
                ->get(),
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
            'memberships' => ['nullable', 'array'],
            'memberships.*.id' => ['required', 'integer', Rule::exists('account_users', 'id')->where('user_id', $user->id)],
            'memberships.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'memberships.*.status' => ['required', Rule::in(['invited', 'active', 'suspended'])],
            'memberships.*.permissions' => ['nullable', 'array'],
            'memberships.*.permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $currentAccountId = blank($validated['current_account_id']) ? null : (int) $validated['current_account_id'];
        $membershipAccountIds = collect($validated['memberships'] ?? [])
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn (int $membershipId) => AccountUser::query()->find($membershipId)?->account_id)
            ->filter()
            ->all();

        if ($currentAccountId !== null && ! in_array($currentAccountId, $membershipAccountIds, true)) {
            return back()->withErrors(['current_account_id' => 'Current account must be one of the user\'s active memberships.']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'global_role' => $validated['global_role'],
            'current_account_id' => $currentAccountId,
        ]);

        foreach ($validated['memberships'] ?? [] as $membership) {
            $record = AccountUser::query()
                ->where('id', $membership['id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $record) {
                continue;
            }

            AccountUser::query()
                ->whereKey($record->id)
                ->update([
                    'role_id' => $membership['role_id'] ?? null,
                    'status' => $membership['status'],
                    'permissions' => array_values(array_unique($membership['permissions'] ?? [])),
                    'accepted_at' => $membership['status'] === 'active' ? ($record->accepted_at ?? now()) : null,
                ]);
        }

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated.');
    }
}
