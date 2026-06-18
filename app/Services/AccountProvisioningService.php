<?php

namespace App\Services;

use App\Models\Account;
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
            'plan_key' => 'starter',
            'credit_balance' => 1000,
            'monthly_credit_allowance' => 1000,
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
