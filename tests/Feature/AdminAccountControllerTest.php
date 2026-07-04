<?php

namespace Tests\Feature;

use App\Models\AIGeneration;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_directory_shows_store_and_owner_context(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $account = Account::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Moonvera',
            'slug' => 'moonvera',
            'plan_key' => 'growth',
            'credit_balance' => 800,
            'status' => 'active',
        ]);

        ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $owner->id,
            'name' => 'Moonvera Store',
            'shop_domain' => 'moonvera.myshopify.com',
            'shop_url' => 'https://moonvera.myshopify.com',
            'status' => 'connected',
        ]);

        $this->actingAs($admin)
            ->get('/admin/accounts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('accounts.data', 1)
                ->where('accounts.data.0.owner.email', 'owner@example.com')
                ->where('accounts.data.0.stores.0.shop_domain', 'moonvera.myshopify.com')
            );
    }

    public function test_customer_detail_includes_tabbed_support_data(): void
    {
        $admin = User::factory()->create(['global_role' => 'super_admin']);
        $owner = User::factory()->create(['name' => 'Owner User', 'email' => 'owner@example.com']);

        $account = Account::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Moonvera',
            'slug' => 'moonvera',
            'plan_key' => 'growth',
            'credit_balance' => 800,
            'monthly_credit_allowance' => 1000,
            'status' => 'active',
        ]);

        $account->users()->attach($owner->id, ['status' => 'active', 'accepted_at' => now()]);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $owner->id,
            'name' => 'Moonvera Store',
            'shop_domain' => 'moonvera.myshopify.com',
            'shop_url' => 'https://moonvera.myshopify.com',
            'status' => 'connected',
            'last_synced_at' => now()->subHour(),
        ]);

        Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'generated_by' => $owner->id,
            'title' => 'Jewelry Sizing Guide',
            'body' => '<p>Helpful content</p>',
            'status' => Blog::STATUS_APPROVED,
        ]);

        UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'shopify_store_id' => $store->id,
            'type' => 'credit_usage',
            'quantity' => 12,
            'unit' => 'credits',
            'metadata' => ['action' => 'blog.generate'],
        ]);

        AIGeneration::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'user_id' => $owner->id,
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'type' => 'blog_generation',
            'status' => 'completed',
            'token_usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 400, 'total_tokens' => 1400],
            'cost' => 0.31,
        ]);

        StoreAnalysis::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'generated_by' => $owner->id,
            'status' => 'failed',
            'error_message' => 'PageSpeed timeout',
        ]);

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $owner->id,
            'shopify_store_id' => $store->id,
            'action' => 'blogs.publish',
            'entity_type' => 'blog',
            'status' => 'failed',
            'description' => 'Publish failed',
        ]);

        $this->actingAs($admin)
            ->get("/admin/accounts/{$account->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounts/Show')
                ->where('account.id', $account->id)
                ->has('stores', 1)
                ->where('stores.0.shop_domain', 'moonvera.myshopify.com')
                ->where('creditsUsedSummary.current_month', 12)
                ->where('aiCostSummary.current_month.estimated_cost', 0.31)
                ->has('recentFailures.analysis', 1)
                ->has('activity', 1)
            );
    }
}
