<?php

namespace App\Support;

use App\Models\Account;
use App\Models\ShopifyStore;

class CatalogAccess
{
    public static function hasSyncedCatalog(Account|int|null $account): bool
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        if (! $accountId) {
            return false;
        }

        return ShopifyStore::query()
            ->forAccount($accountId)
            ->where('status', 'connected')
            ->where(function ($query): void {
                $query->whereHas('products')
                    ->orWhereHas('collections')
                    ->orWhereHas('pages')
                    ->orWhereHas('existingBlogs');
            })
            ->exists();
    }
}
