<?php

namespace App\Models\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAccount
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForAccount(Builder $query, int|Account $account): Builder
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        return $query->where($this->getTable().'.account_id', $accountId);
    }
}
