<?php

namespace App\Policies;

use App\Models\StoreAnalysis;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccounts;

class StoreAnalysisPolicy
{
    use AuthorizesAccounts;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'analysis.run');
    }

    public function view(User $user, StoreAnalysis $analysis): bool
    {
        return $this->can($user, 'analysis.run', $analysis);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'analysis.run');
    }
}
