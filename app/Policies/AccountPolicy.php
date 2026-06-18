<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccounts;

class AccountPolicy
{
    use AuthorizesAccounts;

    public function view(User $user, Account $account): bool
    {
        return $user->belongsToAccount($account);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->can($user, 'accounts.manage', $account->id);
    }

    public function invite(User $user, Account $account): bool
    {
        return $this->can($user, 'team.manage', $account->id);
    }
}
