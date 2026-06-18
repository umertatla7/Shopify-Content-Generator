<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesAccounts
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    protected function can(User $user, string $permission, Model|int|null $resourceOrAccountId = null): bool
    {
        $accountId = is_int($resourceOrAccountId)
            ? $resourceOrAccountId
            : ($resourceOrAccountId->account_id ?? $user->current_account_id);

        return $user->belongsToAccount($accountId)
            && $user->hasAccountPermission($permission, $accountId);
    }
}
