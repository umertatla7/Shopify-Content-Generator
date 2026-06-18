<?php

namespace App\Services;

use App\Models\Account;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UsageTrackingService
{
    public function record(Account|int $account, string $type, int $quantity = 1, string $unit = 'event', ?Model $billable = null, ?User $user = null, array $metadata = []): UsageLog
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        return UsageLog::query()->create([
            'account_id' => $accountId,
            'user_id' => $user?->id,
            'shopify_store_id' => $metadata['shopify_store_id'] ?? null,
            'billable_type' => $billable?->getMorphClass(),
            'billable_id' => $billable?->getKey(),
            'type' => $type,
            'quantity' => $quantity,
            'unit' => $unit,
            'metadata' => $metadata,
        ]);
    }
}
