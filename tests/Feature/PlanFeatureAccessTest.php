<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_module_renders_feature_preview_when_plan_feature_is_unchecked(): void
    {
        [$user] = $this->makeCustomerAccount(features: []);

        $response = $this->actingAs($user)->get('/products');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FeaturePreview')
            ->where('title', 'Products')
        );
    }

    public function test_product_push_is_forbidden_when_plan_feature_is_unchecked(): void
    {
        [$user, $account, $store] = $this->makeCustomerAccount(
            features: [],
            permissions: ['stores.manage']
        );

        $product = Product::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'shopify_product_id' => 'gid://shopify/Product/123',
            'title' => 'Moonstone Ring',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->postJson("/products/{$product->id}/push-content", [
            'generated_title' => 'Moonstone Ring',
            'generated_description' => '<p>Polished for answer engines.</p>',
            'generated_seo_title' => 'Moonstone Ring SEO',
            'generated_seo_description' => 'Moonstone ring SEO description',
            'publish' => false,
        ]);

        $response->assertForbidden();
    }

    private function makeCustomerAccount(array $features, array $permissions = ['stores.view', 'stores.manage', 'stores.sync']): array
    {
        $user = User::factory()->create();

        Plan::query()->create([
            'key' => 'preview-test',
            'name' => 'Preview Test',
            'features' => $features,
            'is_active' => true,
        ]);

        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_key' => 'preview-test',
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

        return [$user, $account, $store];
    }
}
