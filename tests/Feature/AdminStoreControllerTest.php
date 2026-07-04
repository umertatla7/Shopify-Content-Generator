<?php

namespace Tests\Feature;

use App\Models\AIGeneration;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\PublishingLog;
use App\Models\ShopifyCredential;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Models\StoreAnalysis;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_store_support_detail_page(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        [$owner, $account, $store] = $this->makeStoreContext();

        ShopifyCredential::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'admin_api_access_token' => 'token',
            'api_key' => 'key',
            'scopes' => ['read_products', 'write_products'],
            'expires_at' => now()->addDays(30),
        ]);

        AIGeneration::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'user_id' => $owner->id,
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'type' => 'blog_generation',
            'status' => 'completed',
            'token_usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 400, 'total_tokens' => 1600],
            'cost' => 0.42,
        ]);

        UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'shopify_store_id' => $store->id,
            'type' => 'credit_usage',
            'quantity' => 17,
            'unit' => 'credits',
            'metadata' => ['action' => 'blog.generate'],
        ]);

        ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'products',
            'status' => 'failed',
            'error_message' => 'Sync timeout',
        ]);

        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'generated_by' => $owner->id,
            'title' => 'Ring Buying Guide',
            'body' => '<p>Helpful copy</p>',
            'status' => Blog::STATUS_APPROVED,
        ]);

        PublishingLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'blog_id' => $blog->id,
            'user_id' => $owner->id,
            'action' => 'publish',
            'status' => 'failed',
            'error_message' => 'Shopify rejected payload',
        ]);

        StoreAnalysis::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'generated_by' => $owner->id,
            'status' => 'failed',
            'error_message' => 'PageSpeed call failed',
        ]);

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'shopify_store_id' => $store->id,
            'action' => 'products.push',
            'entity_type' => 'product',
            'status' => 'failed',
            'description' => 'Push failed during publish',
        ]);

        $this->actingAs($admin)
            ->get("/admin/stores/{$store->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Stores/Show')
                ->where('store.id', $store->id)
                ->where('creditsUsed.current_month', 17)
                ->where('creditsUsed.all_time', 17)
                ->where('aiCostSummary.current_month.estimated_cost', 0.42)
                ->has('recentFailures.sync', 1)
                ->has('recentFailures.publish', 1)
                ->has('recentFailures.analysis', 1)
                ->has('activity', 1)
                ->has('recentGenerations', 1)
            );
    }

    public function test_platform_admin_can_filter_activity_logs_by_store_module_status_and_date(): void
    {
        $admin = User::factory()->create(['global_role' => 'manager']);
        [$owner, $account, $store] = $this->makeStoreContext();
        [, , $otherStore] = $this->makeStoreContext('Other Account', 'other-store', 'other.myshopify.com');

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'shopify_store_id' => $store->id,
            'action' => 'products.push',
            'entity_type' => 'product',
            'status' => 'failed',
            'description' => 'Product push failed',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        ActivityLog::query()->create([
            'account_id' => $otherStore->account_id,
            'user_id' => $owner->id,
            'shopify_store_id' => $otherStore->id,
            'action' => 'topics.generate',
            'entity_type' => 'topic',
            'status' => 'success',
            'description' => 'Topic generation succeeded',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get('/admin/activity?module=products&status=failed&shopify_store_id='.$store->id.'&date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Activity/Index')
                ->where('filters.module', 'products')
                ->where('filters.status', 'failed')
                ->where('filters.shopify_store_id', (string) $store->id)
                ->has('activity.data', 1)
                ->where('activity.data.0.shopify_store_id', $store->id)
                ->where('activity.data.0.action', 'products.push')
            );
    }

    private function makeStoreContext(
        string $accountName = 'Acme',
        string $slug = 'acme',
        string $shopDomain = 'acme.myshopify.com',
    ): array {
        $owner = User::factory()->create();

        $account = Account::query()->create([
            'owner_id' => $owner->id,
            'name' => $accountName,
            'slug' => $slug,
            'plan_key' => 'growth',
            'credit_balance' => 850,
            'monthly_credit_allowance' => 1000,
            'status' => 'active',
        ]);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $owner->id,
            'name' => $accountName.' Store',
            'shop_domain' => $shopDomain,
            'shop_url' => 'https://'.$shopDomain,
            'status' => 'connected',
            'last_synced_at' => now()->subHour(),
        ]);

        return [$owner, $account, $store];
    }
}
