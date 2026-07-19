<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopifyCollection;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_renders_with_latest_sync_log_loaded(): void
    {
        [$user, $account, $store] = $this->makeCustomerAccount();

        ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'full',
            'status' => 'failed',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(50),
            'error_message' => 'Older failure',
        ]);

        ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'full',
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $response = $this
            ->withHeader('User-Agent', 'Shopify Test Browser')
            ->withSession($this->verifiedShopifySession($account->id))
            ->actingAs($user)
            ->get('/onboarding?shop=acme.myshopify.com&host=test-host');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Index')
            ->where('primaryStore.name', 'Acme Store')
            ->where('primaryStore.latest_sync_log.status', 'completed')
            ->where('shopify.embedded', true)
        );
    }

    public function test_unsynced_accounts_are_redirected_back_to_onboarding_for_locked_modules(): void
    {
        [$user, $account] = $this->makeCustomerAccount();

        $response = $this
            ->withHeader('User-Agent', 'Shopify Test Browser')
            ->withSession($this->verifiedShopifySession($account->id))
            ->actingAs($user)
            ->get('/products?shop=acme.myshopify.com&host=test-host&embedded=1');

        $response->assertRedirect('/onboarding?shop=acme.myshopify.com&host=test-host&embedded=1');
    }

    public function test_unsynced_accounts_can_still_open_billing_before_first_sync(): void
    {
        [$user, $account] = $this->makeCustomerAccount();

        $response = $this
            ->withHeader('User-Agent', 'Shopify Test Browser')
            ->withSession($this->verifiedShopifySession($account->id))
            ->actingAs($user)
            ->get('/billing?shop=acme.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
    }

    public function test_pages_using_latest_sync_log_render_without_ambiguous_columns(): void
    {
        [$user, $account, $store] = $this->makeCustomerAccount();
        Plan::query()->where('key', 'free')->update(['features' => ['all_features']]);
        Product::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'shopify_product_id' => 'gid://shopify/Product/1',
            'title' => 'Test Product',
            'handle' => 'test-product',
        ]);
        ShopifyCollection::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'shopify_collection_id' => 'gid://shopify/Collection/1',
            'title' => 'Test Collection',
            'handle' => 'test-collection',
        ]);
        ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'full',
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $this->withHeader('User-Agent', 'Shopify Test Browser')
            ->withSession($this->verifiedShopifySession($account->id))
            ->actingAs($user);

        foreach (['/onboarding', '/dashboard', '/stores', '/products', '/collections'] as $path) {
            $this->get("{$path}?shop=acme.myshopify.com&host=test-host&embedded=1")->assertOk();
        }

        $latest = $store->fresh()->load('latestSyncLog')->latestSyncLog;
        $this->assertNotNull($latest);
        $this->assertSame('completed', $latest->status);
    }

    private function makeCustomerAccount(): array
    {
        $user = User::factory()->create();

        Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'features' => ['product_descriptions'],
            'monthly_credit_allowance' => 500,
            'is_active' => true,
        ]);

        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_key' => 'free',
            'credit_balance' => 500,
            'monthly_credit_allowance' => 500,
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
            'permissions' => ['stores.view', 'stores.manage', 'stores.sync', 'billing.manage', 'topics.manage', 'blogs.edit', 'blogs.approve'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Acme Store',
            'shop_domain' => 'acme.myshopify.com',
            'shop_url' => 'https://acme.myshopify.com',
            'status' => 'connected',
        ]);

        return [$user, $account, $store];
    }

    private function verifiedShopifySession(int $accountId): array
    {
        return [
            'shopify_verified_context' => [
                'shop' => 'acme.myshopify.com',
                'account_id' => $accountId,
                'sid' => 'verified-test-session',
                'host_hash' => hash('sha256', 'test-host'),
                'user_agent_hash' => hash('sha256', 'Shopify Test Browser'),
                'expires_at' => time() + 60,
            ],
        ];
    }
}
