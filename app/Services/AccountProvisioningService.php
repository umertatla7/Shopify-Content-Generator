<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class AccountProvisioningService
{
    public function createForUser(User $user, string $accountName, string $roleName = 'customer_admin'): Account
    {
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => $accountName,
            'slug' => $this->uniqueSlug($accountName),
            'billing_email' => $user->email,
            'timezone' => config('app.timezone'),
            'plan_key' => 'free',
            'credit_balance' => 500,
            'monthly_credit_allowance' => 500,
        ]);

        $role = Role::query()->where('name', $roleName)->first();

        $account->users()->attach($user->id, [
            'role_id' => $role?->id,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        return $account;
    }

    public function ensureMembership(User $user, Account $account, string $roleName = 'customer_admin'): void
    {
        $membership = AccountUser::query()
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        $role = Role::query()->where('name', $roleName)->first();

        if ($membership) {
            $membership->forceFill([
                'role_id' => $membership->role_id ?: $role?->id,
                'status' => 'active',
                'accepted_at' => $membership->accepted_at ?: now(),
            ])->save();
        } else {
            $account->users()->attach($user->id, [
                'role_id' => $role?->id,
                'status' => 'active',
                'accepted_at' => now(),
            ]);
        }

        if (! $user->current_account_id) {
            $user->forceFill(['current_account_id' => $account->id])->save();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'account';
        $slug = $base;
        $index = 2;

        while (Account::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
