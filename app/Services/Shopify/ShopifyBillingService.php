<?php

namespace App\Services\Shopify;

use App\Models\Account;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\Subscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopifyBillingService
{
    public function __construct(
        private readonly ShopifyService $shopify,
    ) {}

    public function createSubscription(ShopifyStore $store, Plan $plan, string $returnUrl, int $trialDays = 0, ?bool $test = null): array
    {
        if ((float) $plan->monthly_price <= 0) {
            throw new RuntimeException('Free plans do not require a Shopify subscription.');
        }

        $data = $this->shopify->graphql($store, <<<'GRAPHQL'
mutation CreateSubscription(
  $name: String!,
  $lineItems: [AppSubscriptionLineItemInput!]!,
  $returnUrl: URL!,
  $replacementBehavior: AppSubscriptionReplacementBehavior,
  $test: Boolean,
  $trialDays: Int
) {
  appSubscriptionCreate(
    name: $name,
    lineItems: $lineItems,
    returnUrl: $returnUrl,
    replacementBehavior: $replacementBehavior,
    test: $test,
    trialDays: $trialDays
  ) {
    appSubscription {
      id
      name
      status
      test
      trialDays
      returnUrl
      createdAt
      currentPeriodEnd
      lineItems {
        id
      }
    }
    confirmationUrl
    userErrors {
      field
      message
    }
  }
}
GRAPHQL, [
            'name' => $plan->name,
            'lineItems' => [[
                'plan' => [
                    'appRecurringPricingDetails' => [
                        'price' => [
                            'amount' => (float) $plan->monthly_price,
                            'currencyCode' => 'USD',
                        ],
                        'interval' => 'EVERY_30_DAYS',
                    ],
                ],
            ]],
            'returnUrl' => $returnUrl,
            'replacementBehavior' => 'STANDARD',
            'test' => $test ?? (bool) config('services.shopify.billing_test_mode', false),
            'trialDays' => max(0, $trialDays),
        ]);

        $payload = Arr::get($data, 'appSubscriptionCreate', []);
        $this->throwUserErrors(Arr::get($payload, 'userErrors', []));

        return $payload;
    }

    public function cancelSubscription(ShopifyStore $store, string $subscriptionId, bool $prorate = false): array
    {
        $data = $this->shopify->graphql($store, <<<'GRAPHQL'
mutation CancelSubscription($id: ID!, $prorate: Boolean) {
  appSubscriptionCancel(id: $id, prorate: $prorate) {
    appSubscription {
      id
      name
      status
      currentPeriodEnd
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL, [
            'id' => $subscriptionId,
            'prorate' => $prorate,
        ]);

        $payload = Arr::get($data, 'appSubscriptionCancel', []);
        $this->throwUserErrors(Arr::get($payload, 'userErrors', []));

        return $payload;
    }

    public function currentSubscription(ShopifyStore $store): ?array
    {
        $data = $this->shopify->graphql($store, <<<'GRAPHQL'
{
  currentAppInstallation {
    activeSubscriptions {
      id
      name
      status
      test
      trialDays
      createdAt
      currentPeriodEnd
      returnUrl
      lineItems {
        id
        plan {
          pricingDetails {
            ... on AppRecurringPricing {
              price {
                amount
                currencyCode
              }
            }
          }
        }
      }
    }
  }
}
GRAPHQL);

        return Arr::first(Arr::get($data, 'currentAppInstallation.activeSubscriptions', []));
    }

    public function syncAccountSubscription(Account $account, ShopifyStore $store): ?Subscription
    {
        $remote = $this->currentSubscription($store);

        if (! $remote) {
            $this->markAccountAsFree($account);
            $this->markLocalSubscriptionsInactive($account);

            return null;
        }

        return DB::transaction(function () use ($account, $store, $remote): Subscription {
            $plan = $this->resolvePlan($account, $remote);

            if (! $plan) {
                throw new RuntimeException('The active Shopify subscription could not be matched to a local plan.');
            }

            $currentPeriodEnd = filled($remote['currentPeriodEnd'] ?? null) ? Carbon::parse($remote['currentPeriodEnd']) : null;
            $createdAt = filled($remote['createdAt'] ?? null) ? Carbon::parse($remote['createdAt']) : now();
            $trialDays = (int) ($remote['trialDays'] ?? 0);

            $subscription = Subscription::query()->updateOrCreate(
                [
                    'account_id' => $account->id,
                    'external_id' => $remote['id'],
                ],
                [
                    'shopify_store_id' => $store->id,
                    'plan_id' => $plan->id,
                    'provider' => 'shopify',
                    'provider_plan_handle' => $plan->shopify_billing_plan_handle ?: $plan->key,
                    'provider_line_item_id' => Arr::get($remote, 'lineItems.0.id'),
                    'status' => $this->normalizeStatus($remote['status'] ?? 'ACTIVE', $createdAt, $trialDays),
                    'amount' => $plan->monthly_price,
                    'currency' => Arr::get($store->metadata, 'currencyCode', 'USD'),
                    'is_test' => (bool) ($remote['test'] ?? false),
                    'confirmation_url' => null,
                    'return_url' => $remote['returnUrl'] ?? null,
                    'trial_days' => $trialDays,
                    'trial_ends_at' => $trialDays > 0 ? $createdAt->copy()->addDays($trialDays) : null,
                    'current_period_starts_at' => $createdAt,
                    'current_period_ends_at' => $currentPeriodEnd,
                    'cancelled_at' => null,
                    'metadata' => [
                        'remote' => $remote,
                    ],
                ]
            );

            Subscription::query()
                ->where('account_id', $account->id)
                ->where('id', '!=', $subscription->id)
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PENDING])
                ->update([
                    'status' => Subscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            $account->forceFill([
                'plan_key' => $plan->key,
                'monthly_credit_allowance' => (int) ($plan->monthly_credit_allowance ?? $account->monthly_credit_allowance),
                'credit_balance' => max((int) $account->credit_balance, (int) ($plan->monthly_credit_allowance ?? 0)),
                'credits_reset_at' => now(),
                'credits_expire_at' => $plan->credit_expires_after_days ? now()->addDays((int) $plan->credit_expires_after_days) : null,
            ])->save();

            return $subscription->fresh(['plan', 'store']);
        });
    }

    public function activateFreePlan(Account $account): void
    {
        $plan = Plan::query()->where('key', 'free')->first();

        if (! $plan) {
            throw new RuntimeException('The free plan is not configured.');
        }

        DB::transaction(function () use ($account, $plan): void {
            $this->markLocalSubscriptionsInactive($account);
            $this->markAccountAsFree($account, $plan);
        });
    }

    private function resolvePlan(Account $account, array $remote): ?Plan
    {
        $local = Subscription::query()
            ->where('account_id', $account->id)
            ->where('external_id', $remote['id'])
            ->with('plan')
            ->latest('id')
            ->first();

        if ($local?->plan && $this->remoteMatchesPlan($remote, $local->plan)) {
            return $local->plan;
        }

        $pending = Subscription::query()
            ->where('account_id', $account->id)
            ->where('provider', 'shopify')
            ->where('status', Subscription::STATUS_PENDING)
            ->where(function ($query) use ($remote): void {
                $query->where('external_id', $remote['id'])
                    ->orWhere('provider_line_item_id', Arr::get($remote, 'lineItems.0.id'));
            })
            ->with('plan')
            ->latest('id')
            ->first();

        if ($pending?->plan && $this->remoteMatchesPlan($remote, $pending->plan)) {
            return $pending->plan;
        }

        return Plan::query()
            ->where('is_active', true)
            ->get()
            ->first(fn (Plan $plan): bool => $this->remoteMatchesPlan($remote, $plan));
    }

    private function remoteMatchesPlan(array $remote, Plan $plan): bool
    {
        $remoteName = $this->normalizePlanName((string) ($remote['name'] ?? ''));
        $knownNames = array_filter([
            $this->normalizePlanName((string) $plan->name),
            $this->normalizePlanName((string) $plan->key),
            $this->normalizePlanName((string) $plan->shopify_billing_plan_handle),
        ]);

        if ($remoteName === '' || ! in_array($remoteName, $knownNames, true)) {
            return false;
        }

        $remoteAmount = Arr::get($remote, 'lineItems.0.plan.pricingDetails.price.amount');

        if ($remoteAmount !== null && round((float) $remoteAmount, 2) !== round((float) $plan->monthly_price, 2)) {
            return false;
        }

        $remoteCurrency = Arr::get($remote, 'lineItems.0.plan.pricingDetails.price.currencyCode');

        if ($remoteCurrency !== null && strtoupper((string) $remoteCurrency) !== 'USD') {
            return false;
        }

        return true;
    }

    private function normalizePlanName(string $value): string
    {
        return str($value)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();
    }

    private function normalizeStatus(string $status, Carbon $createdAt, int $trialDays): string
    {
        $normalized = strtolower($status);

        if ($normalized === 'active' && $trialDays > 0 && $createdAt->copy()->addDays($trialDays)->isFuture()) {
            return Subscription::STATUS_TRIALING;
        }

        return match ($normalized) {
            'active' => Subscription::STATUS_ACTIVE,
            'cancelled', 'canceled' => Subscription::STATUS_CANCELLED,
            'expired' => Subscription::STATUS_EXPIRED,
            default => $normalized,
        };
    }

    private function markLocalSubscriptionsInactive(Account $account): void
    {
        Subscription::query()
            ->where('account_id', $account->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING, Subscription::STATUS_PENDING])
            ->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
    }

    private function markAccountAsFree(Account $account, ?Plan $freePlan = null): void
    {
        $freePlan ??= Plan::query()->where('key', 'free')->first();

        if (! $freePlan) {
            return;
        }

        $account->forceFill([
            'plan_key' => $freePlan->key,
            'monthly_credit_allowance' => (int) ($freePlan->monthly_credit_allowance ?? $account->monthly_credit_allowance),
            'credit_balance' => max((int) $account->credit_balance, (int) ($freePlan->monthly_credit_allowance ?? 0)),
            'credits_reset_at' => now(),
            'credits_expire_at' => $freePlan->credit_expires_after_days ? now()->addDays((int) $freePlan->credit_expires_after_days) : null,
        ])->save();
    }

    private function throwUserErrors(array $errors): void
    {
        $message = Arr::first(array_filter(array_map(
            fn (array $error) => $error['message'] ?? null,
            $errors
        )));

        if ($message) {
            throw new RuntimeException($message);
        }
    }
}
