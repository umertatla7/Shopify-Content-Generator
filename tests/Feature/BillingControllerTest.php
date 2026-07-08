<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_to_paid_plan_creates_pending_local_subscription_and_redirects_to_shopify_confirmation(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $plan = $this->plan('growth', 29, 'growth', 14);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'appSubscriptionCreate' => [
                        'appSubscription' => [
                            'id' => 'gid://shopify/AppSubscription/1',
                            'name' => 'Growth',
                            'status' => 'PENDING',
                            'test' => true,
                            'trialDays' => 14,
                            'returnUrl' => 'https://portal.test/billing/confirm?plan=growth',
                            'createdAt' => '2026-07-09T00:00:00Z',
                            'currentPeriodEnd' => '2026-08-09T00:00:00Z',
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

        $response = $this->actingAs($user)->post("/billing/plans/{$plan->id}/subscribe");

        $response->assertRedirect('https://shopify.com/confirm/subscription');

        $subscription = Subscription::query()->latest('id')->first();

        $this->assertNotNull($subscription);
        $this->assertSame($user->current_account_id, $subscription->account_id);
        $this->assertSame($store->id, $subscription->shopify_store_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame(Subscription::STATUS_PENDING, $subscription->status);
        $this->assertSame('https://shopify.com/confirm/subscription', $subscription->confirmation_url);
    }

    public function test_subscribe_to_same_active_plan_does_not_create_duplicate_charge(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $plan = $this->plan('growth', 29, 'growth', 14);

        Subscription::query()->create([
            'account_id' => $user->current_account_id,
            'shopify_store_id' => $store->id,
            'plan_id' => $plan->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => 29,
            'currency' => 'USD',
        ]);

        Http::fake();

        $response = $this->actingAs($user)->post("/billing/plans/{$plan->id}/subscribe");

        $response->assertRedirect('/billing');
        $response->assertSessionHas('status', 'Growth is already active for this store.');

        Http::assertNothingSent();
        $this->assertSame(1, Subscription::query()->count());
    }

    public function test_subscribe_to_same_pending_plan_reuses_existing_confirmation_url(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $plan = $this->plan('growth', 29, 'growth', 14);

        Subscription::query()->create([
            'account_id' => $user->current_account_id,
            'shopify_store_id' => $store->id,
            'plan_id' => $plan->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'status' => Subscription::STATUS_PENDING,
            'amount' => 29,
            'currency' => 'USD',
            'confirmation_url' => 'https://shopify.com/confirm/existing',
        ]);

        Http::fake();

        $response = $this->actingAs($user)->post("/billing/plans/{$plan->id}/subscribe");

        $response->assertRedirect('https://shopify.com/confirm/existing');
        Http::assertNothingSent();
        $this->assertSame(1, Subscription::query()->count());
    }

    public function test_subscribe_to_paid_plan_without_shopify_handle_returns_error(): void
    {
        [$user] = $this->memberWithBillingStore();
        $plan = $this->plan('growth', 29, null, 14);

        $response = $this->from('/billing')->actingAs($user)->post("/billing/plans/{$plan->id}/subscribe");

        $response->assertRedirect('/billing');
        $response->assertSessionHasErrors('billing');
    }

    public function test_confirm_syncs_shopify_subscription_and_activates_plan(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $this->plan('free', 0, 'free', 0);
        $plan = $this->plan('growth', 29, 'growth', 14);

        Subscription::query()->create([
            'account_id' => $user->current_account_id,
            'shopify_store_id' => $store->id,
            'plan_id' => $plan->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'status' => Subscription::STATUS_PENDING,
            'amount' => 29,
            'currency' => 'USD',
            'confirmation_url' => 'https://shopify.com/confirm/subscription',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'currentAppInstallation' => [
                        'activeSubscriptions' => [[
                            'id' => 'gid://shopify/AppSubscription/1',
                            'name' => 'Growth',
                            'status' => 'ACTIVE',
                            'test' => true,
                            'trialDays' => 14,
                            'createdAt' => '2026-07-09T00:00:00Z',
                            'currentPeriodEnd' => '2026-08-09T00:00:00Z',
                            'returnUrl' => 'https://portal.test/billing/confirm?plan=growth',
                            'lineItems' => [['id' => 'gid://shopify/AppSubscriptionLineItem/1']],
                        ]],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->get('/billing/confirm?plan=growth');

        $response->assertRedirect('/billing');
        $response->assertSessionHas('status', 'Billing confirmed. Growth is now active.');

        $subscription = Subscription::query()->where('external_id', 'gid://shopify/AppSubscription/1')->first();
        $this->assertNotNull($subscription);
        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertSame('growth', $user->fresh()->currentAccount->plan_key);
        $this->assertDatabaseHas('activity_logs', [
            'account_id' => $user->current_account_id,
            'action' => 'billing.subscription.confirm',
        ]);
    }

    public function test_sync_without_remote_subscription_moves_account_back_to_free(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $free = $this->plan('free', 0, 'free', 0);
        $growth = $this->plan('growth', 29, 'growth', 14);

        $user->currentAccount->update([
            'plan_key' => $growth->key,
            'monthly_credit_allowance' => 1000,
            'credit_balance' => 1000,
        ]);

        Subscription::query()->create([
            'account_id' => $user->current_account_id,
            'shopify_store_id' => $store->id,
            'plan_id' => $growth->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => 29,
            'currency' => 'USD',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'currentAppInstallation' => [
                        'activeSubscriptions' => [],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post('/billing/sync');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Billing synced. No active paid subscription was found, so the account is on Free.');

        $this->assertSame($free->key, $user->fresh()->currentAccount->plan_key);
        $this->assertDatabaseHas('subscriptions', [
            'account_id' => $user->current_account_id,
            'status' => Subscription::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'account_id' => $user->current_account_id,
            'action' => 'billing.subscription.sync',
        ]);
    }

    public function test_cancel_uses_shopify_and_moves_account_back_to_free(): void
    {
        [$user, $store] = $this->memberWithBillingStore();
        $free = $this->plan('free', 0, 'free', 0);
        $growth = $this->plan('growth', 29, 'growth', 14);

        $user->currentAccount->update([
            'plan_key' => $growth->key,
            'monthly_credit_allowance' => 1000,
            'credit_balance' => 1000,
        ]);

        Subscription::query()->create([
            'account_id' => $user->current_account_id,
            'shopify_store_id' => $store->id,
            'plan_id' => $growth->id,
            'provider' => 'shopify',
            'external_id' => 'gid://shopify/AppSubscription/1',
            'provider_plan_handle' => 'growth',
            'status' => Subscription::STATUS_ACTIVE,
            'amount' => 29,
            'currency' => 'USD',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::sequence()
                ->push([
                    'data' => [
                        'appSubscriptionCancel' => [
                            'appSubscription' => [
                                'id' => 'gid://shopify/AppSubscription/1',
                                'name' => 'Growth',
                                'status' => 'CANCELLED',
                                'currentPeriodEnd' => '2026-08-09T00:00:00Z',
                            ],
                            'userErrors' => [],
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'currentAppInstallation' => [
                            'activeSubscriptions' => [],
                        ],
                    ],
                ]),
        ]);

        $response = $this->actingAs($user)->post('/billing/cancel');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Shopify subscription cancelled and account moved back to Free.');

        $this->assertSame($free->key, $user->fresh()->currentAccount->plan_key);
        $this->assertDatabaseHas('activity_logs', [
            'account_id' => $user->current_account_id,
            'action' => 'billing.subscription.cancel',
            'status' => 'success',
        ]);

        $log = ActivityLog::query()->where('action', 'billing.subscription.cancel')->latest('id')->first();
        $this->assertSame('free', $log->new_values['plan_key']);
    }

    private function memberWithBillingStore(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_key' => 'free',
        ]);

        $role = Role::query()->create([
            'name' => 'customer_admin',
            'label' => 'Customer Admin',
        ]);

        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
            'permissions' => ['stores.view', 'stores.manage', 'stores.sync', 'billing.manage'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
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

        return [$user->fresh(), $store->fresh('credential')];
    }

    private function plan(string $key, float $price, ?string $handle, int $trialDays): Plan
    {
        return Plan::query()->updateOrCreate([
            'key' => $key,
        ], [
            'name' => ucfirst($key),
            'monthly_price' => $price,
            'trial_days' => $trialDays,
            'monthly_credit_allowance' => 1000,
            'store_limit' => 1,
            'shopify_billing_plan_handle' => $handle,
            'is_active' => true,
        ]);
    }
}
