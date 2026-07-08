<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyInstallControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_install_start_redirects_to_shopify_authorize(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');
        config()->set('services.shopify.public_app_scopes', ['read_products', 'write_products']);

        $response = $this->get('/shopify/install/start?shop=acme.myshopify.com');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://acme.myshopify.com/admin/oauth/authorize?', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=shopify_key', $response->headers->get('Location'));
        $this->assertStringContainsString(urlencode(route('shopify.oauth.callback')), $response->headers->get('Location'));

        $oauth = session('shopify_oauth');

        $this->assertSame('acme.myshopify.com', $oauth['shop']);
        $this->assertNotEmpty($oauth['state']);
    }

    public function test_shopify_app_for_guest_with_existing_store_restarts_install_flow(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');
        config()->set('services.shopify.public_app_scopes', ['read_products', 'write_products']);

        $owner = $this->memberWithStorePermission();

        ShopifyStore::query()->create([
            'account_id' => $owner->current_account_id,
            'connected_by' => $owner->id,
            'name' => 'Umer Store',
            'shop_domain' => 'umerjewelry.myshopify.com',
            'shop_url' => 'https://umerjewelry.myshopify.com',
            'status' => 'connected',
        ])->credential()->create([
            'account_id' => $owner->current_account_id,
            'admin_api_access_token' => 'token',
            'api_key' => 'shopify_key',
            'client_secret' => 'shopify_secret',
            'scopes' => ['read_products', 'write_products'],
        ]);

        $response = $this->get('/shopify/app?shop=umerjewelry.myshopify.com');

        $response->assertRedirect('/shopify/install/start?shop=umerjewelry.myshopify.com');
    }

    public function test_shopify_app_for_authenticated_user_redirects_to_onboarding_when_store_has_not_been_synced(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');
        config()->set('services.shopify.public_app_scopes', ['read_products', 'write_products']);

        $owner = $this->memberWithStorePermission();

        ShopifyStore::query()->create([
            'account_id' => $owner->current_account_id,
            'connected_by' => $owner->id,
            'name' => 'Umer Store',
            'shop_domain' => 'umerjewelry.myshopify.com',
            'shop_url' => 'https://umerjewelry.myshopify.com',
            'status' => 'connected',
        ])->credential()->create([
            'account_id' => $owner->current_account_id,
            'admin_api_access_token' => 'token',
            'api_key' => 'shopify_key',
            'client_secret' => 'shopify_secret',
            'scopes' => ['read_products', 'write_products'],
        ]);

        $response = $this->actingAs($owner)->get('/shopify/app?shop=umerjewelry.myshopify.com');

        $response->assertRedirect('/onboarding?shop=umerjewelry.myshopify.com');
    }

    public function test_shopify_oauth_callback_provisions_user_account_and_connected_store_for_guest(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        Http::fake([
            'https://acme.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_oauth_token',
                'scope' => 'read_products,write_products',
            ]),
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Acme Store',
                        'myshopifyDomain' => 'acme.myshopify.com',
                        'primaryDomain' => ['url' => 'https://acme.com', 'host' => 'acme.com'],
                        'description' => 'Acme description',
                        'contactEmail' => 'owner@acme.com',
                        'email' => 'owner@acme.com',
                        'currencyCode' => 'USD',
                        'ianaTimezone' => 'America/New_York',
                        'shopAddress' => ['countryCode' => 'US'],
                        'shipsToCountries' => ['US'],
                    ],
                ],
            ]),
        ]);

        $query = [
            'code' => 'code123',
            'shop' => 'acme.myshopify.com',
            'state' => 'nonce123',
            'timestamp' => '1718811498',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'shopify_secret');

        $response = $this->withSession([
            'shopify_oauth' => [
                'state' => 'nonce123',
                'shop' => 'acme.myshopify.com',
                'account_id' => null,
                'user_id' => null,
            ],
        ])->get('/shopify/oauth/callback?'.http_build_query($query));

        $response->assertRedirect('/onboarding?shop=acme.myshopify.com');

        $user = User::query()->where('email', 'owner@acme.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->current_account_id);

        $store = ShopifyStore::query()
            ->where('account_id', $user->current_account_id)
            ->where('shop_domain', 'acme.myshopify.com')
            ->first();

        $this->assertNotNull($store);
        $this->assertSame('connected', $store->status);
        $this->assertSame('Acme Store', $store->name);
        $this->assertSame('shpat_oauth_token', $store->credential->admin_api_access_token);
        $this->assertSame('shopify_key', $store->credential->api_key);
        $this->assertSame(['read_products', 'write_products'], $store->credential->scopes);

        $this->assertAuthenticatedAs($user);
    }

    public function test_shopify_oauth_callback_connects_store_for_existing_account_user(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $user = $this->memberWithStorePermission();

        Http::fake([
            'https://acme.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_oauth_token',
                'scope' => 'read_products,write_products',
            ]),
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Acme Store',
                        'myshopifyDomain' => 'acme.myshopify.com',
                        'primaryDomain' => ['url' => 'https://acme.com', 'host' => 'acme.com'],
                        'description' => 'Acme description',
                        'contactEmail' => 'owner@acme.com',
                        'email' => 'owner@acme.com',
                        'currencyCode' => 'USD',
                        'ianaTimezone' => 'America/New_York',
                        'shopAddress' => ['countryCode' => 'US'],
                        'shipsToCountries' => ['US'],
                    ],
                ],
            ]),
        ]);

        $query = [
            'code' => 'code123',
            'shop' => 'acme.myshopify.com',
            'state' => 'nonce123',
            'timestamp' => '1718811498',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'shopify_secret');

        $response = $this->withSession([
            'shopify_oauth' => [
                'state' => 'nonce123',
                'shop' => 'acme.myshopify.com',
                'account_id' => $user->current_account_id,
                'user_id' => $user->id,
            ],
        ])->actingAs($user)->get('/shopify/oauth/callback?'.http_build_query($query));

        $response->assertRedirect('/onboarding?shop=acme.myshopify.com');

        $store = ShopifyStore::query()->where('account_id', $user->current_account_id)->where('shop_domain', 'acme.myshopify.com')->first();

        $this->assertNotNull($store);
        $this->assertSame('connected', $store->status);
        $this->assertSame('Acme Store', $store->name);
        $this->assertSame('shpat_oauth_token', $store->credential->admin_api_access_token);
    }

    private function memberWithStorePermission(): User
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
            'permissions' => ['stores.view', 'stores.manage', 'stores.sync'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        return $user->fresh();
    }

    private function shopifyHmac(array $parameters, string $secret): string
    {
        ksort($parameters);

        $message = collect($parameters)
            ->reject(fn ($value, $key) => in_array($key, ['hmac', 'signature'], true))
            ->map(fn ($value, $key) => "{$key}=".str_replace('%', '%25', (string) $value))
            ->implode('&');

        return hash_hmac('sha256', $message, $secret);
    }
}
