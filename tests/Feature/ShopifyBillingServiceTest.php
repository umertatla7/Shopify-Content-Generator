<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\Subscription;
use App\Services\Shopify\ShopifyBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_billing_subscription_creation_returns_confirmation_url(): void
    {
        $plan = Plan::query()->updateOrCreate([
            'key' => 'growth',
        ], [
            'name' => 'Growth',
            'monthly_price' => 29,
            'monthly_credit_allowance' => 1000,
            'store_limit' => 1,
            'is_active' => true,
        ]);

        $store = $this->storeWithCredential();

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'appSubscriptionCreate' => [
                        'appSubscription' => [
                            'id' => 'gid://shopify/AppSubscription/1',
                            'name' => 'Growth',
                            'status' => 'ACTIVE',
                            'test' => true,
                            'trialDays' => 7,
                            'returnUrl' => 'https://portal.test/billing/confirm?plan=growth',
                            'createdAt' => '2026-06-21T00:00:00Z',
                            'currentPeriodEnd' => '2026-07-21T00:00:00Z',
                            'lineItems' => [
                                ['id' => 'gid://shopify/AppSubscriptionLineItem/1'],
                            ],
                        ],
                        'confirmationUrl' => 'https://shopify.com/confirm/subscription',
                        'userErrors' => [],
                    ],
                ],
            ]),
        ]);

        $payload = app(ShopifyBillingService::class)->createSubscription(
            $store,
            $plan,
            'https://portal.test/billing/confirm?plan=growth',
            7
        );

        $this->assertSame('https://shopify.com/confirm/subscription', $payload['confirmationUrl']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://acme.myshopify.com/admin/api/2026-04/graphql.json'
                && Str::contains($request->data()['query'] ?? '', 'appSubscriptionCreate')
                && ($request->data()['variables']['lineItems'][0]['plan']['appRecurringPricingDetails']['price']['amount'] ?? null) === 29.0
                && ($request->data()['variables']['test'] ?? null) === true;
        });
    }

    public function test_sync_account_subscription_updates_account_plan_and_local_record(): void
    {
        $freePlan = Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'monthly_price' => 0,
            'monthly_credit_allowance' => 500,
            'store_limit' => 1,
            'is_active' => true,
        ]);

        $growthPlan = Plan::query()->updateOrCreate([
            'key' => 'growth',
        ], [
            'name' => 'Growth',
            'monthly_price' => 29,
            'monthly_credit_allowance' => 1000,
            'store_limit' => 1,
            'shopify_billing_plan_handle' => 'growth',
            'credit_expires_after_days' => 30,
            'is_active' => true,
        ]);

        $store = $this->storeWithCredential();
        $account = $store->account;
        $account->update([
            'plan_key' => $freePlan->key,
            'credit_balance' => 500,
            'monthly_credit_allowance' => 500,
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'currentAppInstallation' => [
                        'activeSubscriptions' => [
                            [
                                'id' => 'gid://shopify/AppSubscription/1',
                                'name' => 'Growth',
                                'status' => 'ACTIVE',
                                'test' => false,
                                'trialDays' => 0,
                                'createdAt' => '2026-06-21T00:00:00Z',
                                'currentPeriodEnd' => '2026-07-21T00:00:00Z',
                                'returnUrl' => 'https://portal.test/billing/confirm?plan=growth',
                                'lineItems' => [
                                    ['id' => 'gid://shopify/AppSubscriptionLineItem/1'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'plan_id' => $growthPlan->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'provider_line_item_id' => 'gid://shopify/AppSubscriptionLineItem/1',
            'status' => Subscription::STATUS_PENDING,
            'amount' => 29,
            'currency' => 'USD',
        ]);

        $subscription = app(ShopifyBillingService::class)->syncAccountSubscription($account, $store);

        $this->assertSame($growthPlan->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('growth', $account->fresh()->plan_key);
        $this->assertSame(1000, $account->fresh()->monthly_credit_allowance);
        $this->assertSame(1000, $account->fresh()->credit_balance);
    }

    private function storeWithCredential(): ShopifyStore
    {
        $account = Account::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'name' => 'Acme Store',
            'shop_domain' => 'acme.myshopify.com',
            'shop_url' => 'https://acme.myshopify.com',
            'status' => 'connected',
            'metadata' => ['currencyCode' => 'USD'],
        ]);

        $store->credential()->create([
            'account_id' => $account->id,
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        return $store->fresh('credential', 'account');
    }
}
