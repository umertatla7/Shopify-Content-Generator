<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\StoreAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyStoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_store_connection_is_disabled_by_default(): void
    {
        config()->set('services.shopify.manual_connection_mode', false);

        $response = $this->actingAs($this->storeManager())->post('/stores', $this->manualStorePayload(
            'acme.myshopify.com'
        ));

        $response->assertNotFound();
    }

    public function test_manual_store_connection_rejects_non_shopify_destinations_without_a_request(): void
    {
        config()->set('services.shopify.manual_connection_mode', true);
        Http::preventStrayRequests();

        $response = $this->actingAs($this->storeManager())->post('/stores', $this->manualStorePayload(
            'http://127.0.0.1:8080/internal'
        ));

        $response->assertSessionHasErrors('shop_url');
        Http::assertNothingSent();
    }

    public function test_store_audit_uses_the_verified_shop_domain_instead_of_a_saved_arbitrary_url(): void
    {
        config()->set('services.ai.provider', 'stub');
        config()->set('services.pagespeed.enabled', false);

        Http::fake([
            'https://safe-store.myshopify.com' => Http::response(
                '<html><head><title>Safe Store</title></head><body><h1>Safe Store</h1></body></html>',
                200,
            ),
        ]);

        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Safe Store Account',
            'slug' => 'safe-store-account',
            'plan_key' => 'free',
        ]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Safe Store',
            'shop_domain' => 'safe-store.myshopify.com',
            'shop_url' => 'http://127.0.0.1/internal',
            'status' => 'connected',
        ]);

        $analysis = app(StoreAnalysisService::class)->analyze($store, $user);

        $this->assertSame('https://safe-store.myshopify.com', $analysis->response['homepage_report']['url']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://safe-store.myshopify.com');
        Http::assertSentCount(1);
    }

    public function test_store_audit_rejects_a_legacy_non_shopify_domain_without_a_request(): void
    {
        config()->set('services.ai.provider', 'stub');
        config()->set('services.pagespeed.enabled', false);
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Legacy Account',
            'slug' => 'legacy-account',
            'plan_key' => 'free',
        ]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Legacy Store',
            'shop_domain' => '127.0.0.1',
            'shop_url' => 'http://127.0.0.1/internal',
            'status' => 'connected',
        ]);

        $analysis = app(StoreAnalysisService::class)->analyze($store, $user);

        $this->assertNull($analysis->response['homepage_report']['url']);
        $this->assertSame('failed', $analysis->response['homepage_report']['status']);
        Http::assertNothingSent();
    }

    private function storeManager(): User
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme-'.$user->id,
            'plan_key' => 'free',
        ]);
        $role = Role::query()->firstOrCreate([
            'name' => 'customer_admin',
        ], [
            'label' => 'Customer Admin',
        ]);

        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
            'permissions' => ['stores.view', 'stores.manage', 'stores.sync'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        return $user->fresh();
    }

    private function manualStorePayload(string $shopUrl): array
    {
        return [
            'name' => 'Acme Store',
            'shop_url' => $shopUrl,
            'admin_api_access_token' => 'shpat_test',
            'default_language' => 'en',
        ];
    }
}
