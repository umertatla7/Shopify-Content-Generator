<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Notifications\NewShopifySignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    public function test_shopify_install_start_renders_embedded_bounce_page_for_embedded_requests(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');
        config()->set('services.shopify.public_app_scopes', ['read_products', 'write_products']);

        $response = $this->get('/shopify/install/start?shop=acme.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
        $response->assertSee('Redirecting to Shopify', false);
        $response->assertSee('https://acme.myshopify.com/admin/oauth/authorize?', false);

        $oauth = session('shopify_oauth');

        $this->assertNotEmpty($oauth['state']);
        $this->assertSame($oauth, Cache::get('shopify_oauth:'.$oauth['state']));
    }

    public function test_shopify_install_start_allows_embedded_second_store_install_even_when_current_account_is_full(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');
        config()->set('services.shopify.public_app_scopes', ['read_products', 'write_products']);

        $owner = $this->memberWithStorePermission();

        ShopifyStore::query()->create([
            'account_id' => $owner->current_account_id,
            'connected_by' => $owner->id,
            'name' => 'Existing Store',
            'shop_domain' => 'existing-store.myshopify.com',
            'shop_url' => 'https://existing-store.myshopify.com',
            'status' => 'connected',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get('/shopify/install/start?shop=second-store.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
        $response->assertSee('Redirecting to Shopify', false);
        $response->assertSee('https://second-store.myshopify.com/admin/oauth/authorize?', false);
    }

    public function test_shopify_app_for_guest_with_existing_store_uses_embedded_bounce_page_for_session_restore(): void
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

        $host = base64_encode('admin.shopify.com/store/umer-jewelry');

        $response = $this->get('/shopify/app?shop=umerjewelry.myshopify.com&host='.$host.'&embedded=1');

        $response->assertOk();
        $response->assertSee('Opening GrowShopHigh', false);
        $response->assertSee('/shopify/session', false);
        $response->assertSee('value="umerjewelry.myshopify.com"', false);
        $response->assertDontSee('install_token=', false);
        $response->assertDontSee('/shopify/install/start', false);
        $this->assertGuest();
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
        config()->set('services.app_review.support_email', 'support@growshophigh.com');
        Notification::fake();

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

        Notification::assertSentOnDemand(NewShopifySignupNotification::class, function ($notification, $channels, $notifiable) use ($user, $store): bool {
            return in_array('mail', $channels, true)
                && $notifiable->routes['mail'] === 'support@growshophigh.com'
                && $notification->toMail($notifiable)->subject === 'New GrowShopHigh Shopify signup'
                && $user->email === 'owner@acme.com'
                && $store->shop_domain === 'acme.myshopify.com';
        });
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

        $store = ShopifyStore::query()
            ->where('account_id', $user->fresh()->current_account_id)
            ->where('shop_domain', 'acme.myshopify.com')
            ->first();

        $this->assertNotNull($store);
        $this->assertSame('connected', $store->status);
        $this->assertSame('Acme Store', $store->name);
        $this->assertSame('shpat_oauth_token', $store->credential->admin_api_access_token);
    }

    public function test_shopify_oauth_callback_creates_new_account_when_guest_email_already_exists_but_current_account_is_full(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $user = $this->memberWithStorePermission();
        $user->forceFill([
            'email' => 'owner@acme.com',
            'name' => 'Existing Owner',
        ])->save();

        ShopifyStore::query()->create([
            'account_id' => $user->current_account_id,
            'connected_by' => $user->id,
            'name' => 'Existing Store',
            'shop_domain' => 'existing-store.myshopify.com',
            'shop_url' => 'https://existing-store.myshopify.com',
            'status' => 'connected',
        ]);

        Http::fake([
            'https://acme-two.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_oauth_token',
                'scope' => 'read_products,write_products',
            ]),
            'https://acme-two.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Acme Two',
                        'myshopifyDomain' => 'acme-two.myshopify.com',
                        'primaryDomain' => ['url' => 'https://acme-two.com', 'host' => 'acme-two.com'],
                        'description' => 'Acme Two description',
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
            'shop' => 'acme-two.myshopify.com',
            'state' => 'nonce123',
            'timestamp' => '1718811498',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'shopify_secret');

        $response = $this->withSession([
            'shopify_oauth' => [
                'state' => 'nonce123',
                'shop' => 'acme-two.myshopify.com',
                'account_id' => null,
                'user_id' => null,
            ],
        ])->get('/shopify/oauth/callback?'.http_build_query($query));

        $response->assertRedirect('/onboarding?shop=acme-two.myshopify.com');

        $user->refresh();
        $newStore = ShopifyStore::query()
            ->where('shop_domain', 'acme-two.myshopify.com')
            ->first();

        $this->assertNotNull($newStore);
        $this->assertSame(2, Account::query()->count());
        $this->assertNotSame($newStore->account_id, $user->fresh()->current_account_id);
        $this->assertDatabaseHas('accounts', [
            'id' => $newStore->account_id,
        ]);
        $this->assertDatabaseMissing('accounts', [
            'id' => $newStore->account_id,
            'owner_id' => $user->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'owner+acme-two@acme.com',
        ]);
    }

    public function test_shopify_oauth_callback_can_use_cached_oauth_state_when_session_is_missing(): void
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

        Cache::put('shopify_oauth:nonce123', [
            'state' => 'nonce123',
            'shop' => 'acme.myshopify.com',
            'account_id' => null,
            'user_id' => null,
        ], now()->addMinutes(15));

        $query = [
            'code' => 'code123',
            'shop' => 'acme.myshopify.com',
            'state' => 'nonce123',
            'timestamp' => '1718811498',
            'host' => 'YWRtaW4uc2hvcGlmeS5jb20vc3RvcmUvYWNtZQ',
        ];
        $query['hmac'] = $this->shopifyHmac($query, 'shopify_secret');

        $response = $this->get('/shopify/oauth/callback?'.http_build_query($query));

        $response->assertOk();
        $response->assertSee('Redirecting back to Shopify', false);
        $this->assertStringContainsString(
            'https:\/\/admin.shopify.com\/store\/acme\/apps\/shopify_key\/shopify\/app?shop=acme.myshopify.com\u0026host=YWRtaW4uc2hvcGlmeS5jb20vc3RvcmUvYWNtZQ\u0026embedded=1',
            $response->getContent()
        );
        $this->assertStringNotContainsString('install_token=', $response->getContent());
        $this->assertNull(Cache::get('shopify_oauth:nonce123'));
        $this->assertAuthenticated();
    }

    public function test_shopify_session_token_creates_embedded_session_for_existing_store(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $owner = $this->memberWithStorePermission();

        $store = ShopifyStore::query()->create([
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

        $token = $this->shopifySessionToken('umerjewelry.myshopify.com', 'shopify_key', 'shopify_secret');

        $response = $this->post('/shopify/session', [
            'shop' => 'umerjewelry.myshopify.com',
            'host' => 'test-host',
            'embedded' => '1',
            'id_token' => $token,
        ]);

        $response->assertRedirect('/onboarding?shop=umerjewelry.myshopify.com&host=test-host&embedded=1');
        $this->assertAuthenticatedAs($owner->fresh());
        $this->assertSame($store->account_id, $owner->fresh()->current_account_id);
    }

    public function test_shopify_session_token_rejects_wrong_shop(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $token = $this->shopifySessionToken('other.myshopify.com', 'shopify_key', 'shopify_secret');

        $response = $this->post('/shopify/session', [
            'shop' => 'umerjewelry.myshopify.com',
            'host' => 'test-host',
            'embedded' => '1',
            'id_token' => $token,
        ]);

        $response->assertSessionHasErrors('shopify');
        $this->assertGuest();
    }

    public function test_embedded_request_authenticates_with_a_valid_shopify_session_token_without_a_cookie_session(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

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

        $token = $this->shopifySessionToken('umerjewelry.myshopify.com', 'shopify_key', 'shopify_secret');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->get('/billing?shop=umerjewelry.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
        $response->assertSee('Billing', false);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_embedded_request_rejects_an_invalid_shopify_session_token(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $response = $this
            ->withHeader('Authorization', 'Bearer invalid-session-token')
            ->get('/billing?shop=umerjewelry.myshopify.com&host=test-host&embedded=1');

        $response
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid Shopify session token format.']);

        $this->assertGuest();
    }

    public function test_embedded_session_token_selects_only_its_own_store_account(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.public_app_client_secret', 'shopify_secret');

        $firstOwner = $this->memberWithStorePermission();
        $firstStore = ShopifyStore::query()->create([
            'account_id' => $firstOwner->current_account_id,
            'connected_by' => $firstOwner->id,
            'name' => 'First Store',
            'shop_domain' => 'first-store.myshopify.com',
            'shop_url' => 'https://first-store.myshopify.com',
            'status' => 'connected',
        ]);
        $firstStore->credential()->create([
            'account_id' => $firstOwner->current_account_id,
            'admin_api_access_token' => 'first-token',
            'api_key' => 'shopify_key',
            'client_secret' => 'shopify_secret',
            'scopes' => ['read_products'],
        ]);

        $secondOwner = $this->memberWithStorePermission();
        $secondStore = ShopifyStore::query()->create([
            'account_id' => $secondOwner->current_account_id,
            'connected_by' => $secondOwner->id,
            'name' => 'Second Store',
            'shop_domain' => 'second-store.myshopify.com',
            'shop_url' => 'https://second-store.myshopify.com',
            'status' => 'connected',
        ]);
        $secondStore->credential()->create([
            'account_id' => $secondOwner->current_account_id,
            'admin_api_access_token' => 'second-token',
            'api_key' => 'shopify_key',
            'client_secret' => 'shopify_secret',
            'scopes' => ['read_products'],
        ]);

        $token = $this->shopifySessionToken('second-store.myshopify.com', 'shopify_key', 'shopify_secret');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->get('/billing?shop=first-store.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
        $response->assertSee('Second Store', false);
        $response->assertDontSee('First Store', false);
        $this->assertSame($firstStore->account_id, $firstOwner->fresh()->current_account_id);
        $this->assertSame($secondStore->account_id, $secondOwner->fresh()->current_account_id);
    }

    public function test_authenticated_requests_switch_current_account_based_on_shop_domain(): void
    {
        $user = $this->memberWithStorePermission();

        ShopifyStore::query()->create([
            'account_id' => $user->current_account_id,
            'connected_by' => $user->id,
            'name' => 'Primary Store',
            'shop_domain' => 'primary-store.myshopify.com',
            'shop_url' => 'https://primary-store.myshopify.com',
            'status' => 'connected',
        ]);

        $secondAccount = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Second Workspace',
            'slug' => 'second-workspace',
            'plan_key' => 'free',
        ]);

        AccountUser::query()->create([
            'account_id' => $secondAccount->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('name', 'customer_admin')->value('id'),
            'status' => 'active',
            'accepted_at' => now(),
            'permissions' => ['stores.view', 'stores.manage', 'stores.sync'],
        ]);

        ShopifyStore::query()->create([
            'account_id' => $secondAccount->id,
            'connected_by' => $user->id,
            'name' => 'Second Store',
            'shop_domain' => 'second-store.myshopify.com',
            'shop_url' => 'https://second-store.myshopify.com',
            'status' => 'connected',
        ]);

        $response = $this->actingAs($user)->get('/billing?shop=second-store.myshopify.com&host=test-host&embedded=1');

        $response->assertOk();
        $this->assertSame($secondAccount->id, $user->fresh()->current_account_id);
    }

    private function memberWithStorePermission(): User
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

    private function shopifyHmac(array $parameters, string $secret): string
    {
        ksort($parameters);

        $message = collect($parameters)
            ->reject(fn ($value, $key) => in_array($key, ['hmac', 'signature'], true))
            ->map(fn ($value, $key) => "{$key}=".str_replace('%', '%25', (string) $value))
            ->implode('&');

        return hash_hmac('sha256', $message, $secret);
    }

    private function shopifySessionToken(string $shop, string $audience, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => "https://{$shop}/admin",
            'dest' => "https://{$shop}",
            'aud' => $audience,
            'sub' => 'gid://shopify/User/1',
            'exp' => time() + 60,
            'nbf' => time() - 60,
            'iat' => time(),
            'jti' => 'test-jti',
            'sid' => 'test-session',
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
