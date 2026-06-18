<?php

namespace App\Policies;

use App\Models\ShopifyStore;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccounts;

class ShopifyStorePolicy
{
    use AuthorizesAccounts;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'stores.view');
    }

    public function view(User $user, ShopifyStore $store): bool
    {
        return $this->can($user, 'stores.view', $store);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'stores.manage');
    }

    public function update(User $user, ShopifyStore $store): bool
    {
        return $this->can($user, 'stores.manage', $store);
    }

    public function sync(User $user, ShopifyStore $store): bool
    {
        return $this->can($user, 'stores.sync', $store);
    }

    public function delete(User $user, ShopifyStore $store): bool
    {
        return $this->can($user, 'stores.manage', $store);
    }
}
