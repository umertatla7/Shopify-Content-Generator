<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\Shopify\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_content_ignores_publish_flag_when_saving_local_product(): void
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
            'permissions' => ['stores.manage'],
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

        $product = Product::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'shopify_product_id' => 'gid://shopify/Product/123',
            'title' => 'Original product',
            'status' => 'draft',
        ]);

        $this->mock(ShopifyService::class, function ($mock): void {
            $mock->shouldReceive('updateProductContent')
                ->once()
                ->andReturn([
                    'title' => 'Updated product',
                    'descriptionHtml' => '<p>Updated description.</p>',
                    'status' => 'DRAFT',
                ]);
        });

        $response = $this->actingAs($user)->postJson("/products/{$product->id}/push-content", [
            'generated_title' => 'Updated product',
            'generated_description' => '<p>Updated description.</p>',
            'generated_seo_title' => 'SEO title',
            'generated_seo_description' => 'SEO description',
            'publish' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Product content pushed to Shopify.');

        $product->refresh();

        $this->assertSame('Updated product', $product->generated_title);
        $this->assertSame('<p>Updated description.</p>', $product->generated_description);
        $this->assertSame('SEO title', $product->generated_seo_title);
        $this->assertSame('SEO description', $product->generated_seo_description);
        $this->assertNull($product->shopify_content_push_error);
        $this->assertNotNull($product->shopify_content_pushed_at);
    }
}
