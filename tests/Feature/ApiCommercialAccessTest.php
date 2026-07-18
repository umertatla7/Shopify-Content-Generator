<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ShopifyStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCommercialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_analysis_requires_analysis_permission(): void
    {
        [$user, , $store] = $this->workspace(['store_audit'], ['stores.view']);
        $this->addCatalogProduct($store);
        Sanctum::actingAs($user);

        $this->postJson("/api/stores/{$store->id}/analysis")
            ->assertForbidden();
    }

    public function test_api_analysis_cannot_bypass_disabled_plan_feature(): void
    {
        [$user, , $store] = $this->workspace([], ['stores.view', 'analysis.run']);
        $this->addCatalogProduct($store);
        Sanctum::actingAs($user);

        $this->postJson("/api/stores/{$store->id}/analysis")
            ->assertForbidden();
    }

    public function test_api_topic_generation_requires_catalog_sync(): void
    {
        [$user, , $store] = $this->workspace(['monthly_blog_generation'], ['stores.view', 'topics.manage']);
        Sanctum::actingAs($user);

        $this->postJson("/api/stores/{$store->id}/topics", ['count' => 1])
            ->assertStatus(409)
            ->assertJson(['message' => 'Sync the Shopify catalog first.']);
    }

    public function test_api_store_list_requires_store_view_permission(): void
    {
        [$user] = $this->workspace([], []);
        Sanctum::actingAs($user);

        $this->getJson('/api/stores')->assertForbidden();
    }

    private function workspace(array $features, array $permissions): array
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'key' => 'api-plan-'.uniqid(),
            'name' => 'API Plan',
            'features' => $features,
            'monthly_seo_report_limit' => 10,
            'monthly_topic_limit' => 10,
            'monthly_blog_limit' => 10,
            'monthly_credit_allowance' => 1000,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'API Store',
            'slug' => 'api-store-'.uniqid(),
            'plan_key' => $plan->key,
            'credit_balance' => 1000,
        ]);
        $account->users()->attach($user->id, [
            'status' => 'active',
            'permissions' => json_encode($permissions),
            'accepted_at' => now(),
        ]);
        $user->update(['current_account_id' => $account->id]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'API Store',
            'shop_domain' => 'api-store-'.uniqid().'.myshopify.com',
            'shop_url' => 'https://api-store.myshopify.com',
            'status' => 'connected',
        ]);

        return [$user->refresh(), $account, $store];
    }

    private function addCatalogProduct(ShopifyStore $store): void
    {
        Product::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'shopify_product_id' => 'gid://shopify/Product/1',
            'title' => 'Synced product',
        ]);
    }
}
