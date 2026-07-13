<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Blog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_update_can_store_target_word_count_in_payload(): void
    {
        [$user, $account, $store] = $this->makeCustomerAccount(['monthly_blog_generation'], ['blogs.edit', 'blogs.review']);

        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Sizing Guide',
            'body' => '<p>Existing body content.</p>',
            'status' => Blog::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($user)->patch("/blogs/{$blog->id}", [
            'title' => 'Sizing Guide',
            'meta_title' => 'Sizing Guide',
            'meta_description' => 'Helpful sizing guide',
            'slug' => 'sizing-guide',
            'excerpt' => 'Helpful sizing guide',
            'body' => '<p>Existing body content.</p>',
            'featured_image_idea' => '',
            'primary_keyword' => 'ring sizing',
            'secondary_keywords' => [],
            'faq' => [],
            'internal_links' => [],
            'product_links' => [],
            'target_word_count' => 1500,
            'status' => Blog::STATUS_DRAFT,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Blog updated.');

        $blog->refresh();

        $this->assertSame(1500, $blog->payload['target_word_count'] ?? null);
    }

    public function test_blog_update_respects_plan_blog_word_cap(): void
    {
        [$user, $account, $store] = $this->makeCustomerAccount(['monthly_blog_generation'], ['blogs.edit', 'blogs.review'], [
            'max_blog_word_count' => 1200,
        ]);

        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Sizing Guide',
            'body' => '<p>Existing body content.</p>',
            'status' => Blog::STATUS_DRAFT,
        ]);

        $response = $this->from("/blogs/{$blog->id}/edit")->actingAs($user)->patch("/blogs/{$blog->id}", [
            'title' => 'Sizing Guide',
            'meta_title' => 'Sizing Guide',
            'meta_description' => 'Helpful sizing guide',
            'slug' => 'sizing-guide',
            'excerpt' => 'Helpful sizing guide',
            'body' => '<p>Existing body content.</p>',
            'featured_image_idea' => '',
            'primary_keyword' => 'ring sizing',
            'secondary_keywords' => [],
            'faq' => [],
            'internal_links' => [],
            'product_links' => [],
            'target_word_count' => 1500,
            'status' => Blog::STATUS_DRAFT,
        ]);

        $response->assertRedirect("/blogs/{$blog->id}/edit");
        $response->assertSessionHasErrors('target_word_count');
    }

    public function test_blog_catalog_sync_can_run_for_selected_store(): void
    {
        [$user, , $store] = $this->makeCustomerAccount(['monthly_blog_generation'], ['blogs.review']);

        $mock = Mockery::mock(ShopifySyncService::class);
        $mock->shouldReceive('syncPortalBlogs')
            ->once()
            ->andReturn([
                'shopify_articles' => 5,
                'matched' => 4,
                'missing' => 1,
            ]);

        $this->app->instance(ShopifySyncService::class, $mock);

        $response = $this->actingAs($user)->post('/blogs/sync-shopify', [
            'store_id' => $store->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Shopify blog sync checked 1 store(s), found 5 Shopify article(s), matched 4 portal blog(s), and reset 1 missing blog(s).');
    }

    private function makeCustomerAccount(array $features, array $permissions, array $planOverrides = []): array
    {
        $user = User::factory()->create();

        Plan::query()->create([
            'key' => 'blog-test',
            'name' => 'Blog Test',
            'features' => $features,
            'is_active' => true,
            ...$planOverrides,
        ]);

        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_key' => 'blog-test',
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
            'permissions' => $permissions,
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

        $store->credential()->create([
            'account_id' => $account->id,
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        Product::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'shopify_product_id' => 'gid://shopify/Product/1',
            'title' => 'Synced product',
            'status' => 'active',
        ]);

        return [$user, $account, $store];
    }
}
