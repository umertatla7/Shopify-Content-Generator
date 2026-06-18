<?php

namespace App\Services;

use App\Models\Account;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditService
{
    public const WORDS_PER_CREDIT = 15;

    public const TOPIC_CREDITS = 2;

    /**
     * @var array<string, int>
     */
    private const PRODUCT_CONTENT_CREDITS = [
        'short' => 10,
        'balanced' => 14,
        'bullets' => 16,
        'long' => 20,
    ];

    private const COLLECTION_CONTENT_CREDITS = [
        'short' => 14,
        'balanced' => 18,
        'long' => 24,
    ];

    public function summary(Account|int|null $account): array
    {
        $account = $this->account($account);
        $balance = (int) ($account?->credit_balance ?? 0);
        $allowance = (int) ($account?->monthly_credit_allowance ?? 0);

        return [
            'balance' => $balance,
            'monthly_allowance' => $allowance,
            'used' => max(0, $allowance - $balance),
            'estimated_words_left' => $balance * self::WORDS_PER_CREDIT,
            'words_per_credit' => self::WORDS_PER_CREDIT,
            'credits_reset_at' => $account?->credits_reset_at,
        ];
    }

    public function topicGenerationCost(int $topicCount): int
    {
        return max(1, $topicCount) * self::TOPIC_CREDITS;
    }

    public function productContentCost(string $style): int
    {
        return self::PRODUCT_CONTENT_CREDITS[$style] ?? self::PRODUCT_CONTENT_CREDITS['balanced'];
    }

    public function collectionContentCost(string $style): int
    {
        return self::COLLECTION_CONTENT_CREDITS[$style] ?? self::COLLECTION_CONTENT_CREDITS['balanced'];
    }

    public function wordsToCredits(int $words): int
    {
        return max(1, (int) ceil(max(1, $words) / self::WORDS_PER_CREDIT));
    }

    public function ensure(Account|int|null $account, int $credits, string $label = 'this action'): void
    {
        $account = $this->account($account);

        if (! $account) {
            throw new RuntimeException('No customer account is selected.');
        }

        if ((int) $account->credit_balance < $credits) {
            throw new RuntimeException("Not enough credits for {$label}. Required: {$credits}, available: {$account->credit_balance}.");
        }
    }

    public function charge(Account|int $account, string $action, int $credits, ?Model $billable = null, ?User $user = null, array $metadata = []): UsageLog
    {
        return DB::transaction(function () use ($account, $action, $credits, $billable, $user, $metadata): UsageLog {
            $accountId = $account instanceof Account ? $account->id : $account;
            $lockedAccount = Account::query()->lockForUpdate()->findOrFail($accountId);

            if ((int) $lockedAccount->credit_balance < $credits) {
                throw new RuntimeException("Not enough credits. Required: {$credits}, available: {$lockedAccount->credit_balance}.");
            }

            $lockedAccount->decrement('credit_balance', $credits);

            return UsageLog::query()->create([
                'account_id' => $lockedAccount->id,
                'user_id' => $user?->id,
                'shopify_store_id' => $metadata['shopify_store_id'] ?? null,
                'billable_type' => $billable?->getMorphClass(),
                'billable_id' => $billable?->getKey(),
                'type' => 'credit_usage',
                'quantity' => $credits,
                'unit' => 'credit',
                'metadata' => [
                    ...$metadata,
                    'action' => $action,
                    'words_per_credit' => self::WORDS_PER_CREDIT,
                ],
            ]);
        });
    }

    private function account(Account|int|null $account): ?Account
    {
        if ($account instanceof Account || $account === null) {
            return $account;
        }

        return Account::query()->find($account);
    }
}
