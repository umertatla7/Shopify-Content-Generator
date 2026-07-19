<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Blog;
use App\Models\ShopifyStore;
use App\Services\Shopify\ShopifyService;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyGraphqlServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_credentials_can_be_saved_before_access_token_exists(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => null,
            'api_key' => 'client_id',
            'client_secret' => 'client_secret',
        ]);

        $credential = $store->credential()->firstOrFail();

        $this->assertNull($credential->admin_api_access_token);
        $this->assertSame('client_id', $credential->api_key);
        $this->assertSame('client_secret', $credential->client_secret);
        $this->assertArrayNotHasKey('admin_api_access_token', $credential->toArray());
        $this->assertArrayNotHasKey('refresh_token', $credential->toArray());
    }

    public function test_empty_graphql_variables_are_sent_as_an_object(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Acme Store',
                        'myshopifyDomain' => 'acme.myshopify.com',
                    ],
                ],
            ]),
        ]);

        app(ShopifyService::class)->validateConnection($store->fresh('credential'));

        Http::assertSent(fn ($request) => Str::contains($request->body(), '"variables":{}'));
    }

    public function test_expired_offline_token_is_refreshed_before_graphql_request(): void
    {
        config()->set('services.shopify.public_app_api_key', 'client_id');
        config()->set('services.shopify.public_app_client_secret', 'client_secret');

        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'old_token',
            'refresh_token' => 'old_refresh_token',
            'expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->addDays(30),
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'new_token',
                'refresh_token' => 'new_refresh_token',
                'scope' => 'read_products,write_content',
                'expires_in' => 3600,
                'refresh_token_expires_in' => 7776000,
            ]),
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Acme Store',
                        'myshopifyDomain' => 'acme.myshopify.com',
                    ],
                ],
            ]),
        ]);

        app(ShopifyService::class)->validateConnection($store->fresh('credential'));

        $credential = $store->credential()->firstOrFail();

        $this->assertSame('new_token', $credential->admin_api_access_token);
        $this->assertSame('new_refresh_token', $credential->refresh_token);
        $this->assertSame(['read_products', 'write_content'], $credential->scopes);
        $this->assertTrue($credential->expires_at->isFuture());

        Http::assertSent(fn ($request) => $request->url() === 'https://acme.myshopify.com/admin/oauth/access_token'
            && ($request->data()['grant_type'] ?? null) === 'refresh_token'
            && ($request->data()['refresh_token'] ?? null) === 'old_refresh_token'
            && ($request->data()['client_id'] ?? null) === 'client_id');

        Http::assertSent(fn ($request) => $request->url() === 'https://acme.myshopify.com/admin/api/2026-04/graphql.json'
            && $request->hasHeader('X-Shopify-Access-Token', 'new_token'));
    }

    public function test_expired_refresh_token_returns_a_reconnectable_store_state(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'expired_access_token',
            'refresh_token' => 'expired_refresh_token',
            'expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->subMinute(),
        ]);
        Http::preventStrayRequests();

        $log = app(ShopifySyncService::class)->syncStore($store->fresh('credential'));

        $this->assertSame('reconnect_required', $store->fresh()->status);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Reconnect this store', $log->error_message);
        $this->assertStringNotContainsString('expired_access_token', $log->error_message);
        $this->assertStringNotContainsString('expired_refresh_token', $log->error_message);
        Http::assertNothingSent();
    }

    public function test_legacy_non_expiring_token_rejection_becomes_reconnectable_without_leaking_token(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'legacy_secret_token',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'errors' => [[
                    'message' => '[API] Non-expiring access tokens are no longer accepted for the Admin API.',
                ]],
            ]),
        ]);

        $log = app(ShopifySyncService::class)->syncStore($store->fresh('credential'));

        $this->assertSame('reconnect_required', $store->fresh()->status);
        $this->assertSame('failed', $log->status);
        $this->assertSame(
            'Shopify authorization expired. Reconnect this store from Shopify admin.',
            $log->error_message,
        );
        $this->assertStringNotContainsString('legacy_secret_token', $log->error_message);
    }

    public function test_transient_shopify_failure_does_not_disconnect_the_store(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'temporary_token',
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([], 503),
        ]);

        $log = app(ShopifySyncService::class)->syncStore($store->fresh('credential'));

        $this->assertSame('connected', $store->fresh()->status);
        $this->assertSame('failed', $log->status);
        $this->assertSame('Shopify Admin API request failed with status 503.', $log->error_message);
        $this->assertStringNotContainsString('temporary_token', $log->error_message);
    }

    public function test_article_publishing_uses_admin_graphql(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        $blog = Blog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'title' => 'Summer Buying Guide',
            'meta_title' => 'Summer Buying Guide',
            'meta_description' => 'A useful summer buying guide.',
            'slug' => 'summer-buying-guide',
            'excerpt' => 'A useful summer guide.',
            'body' => '<p>Choose the right product for summer.</p>',
            'primary_keyword' => 'summer products',
            'secondary_keywords' => ['buying guide'],
            'shopify_blog_id' => 'gid://shopify/Blog/100',
            'status' => Blog::STATUS_APPROVED,
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'articleCreate' => [
                        'article' => [
                            'id' => 'gid://shopify/Article/200',
                            'title' => 'Summer Buying Guide',
                            'handle' => 'summer-buying-guide',
                            'body' => '<p>Choose the right product for summer.</p>',
                            'summary' => 'A useful summer guide.',
                            'tags' => ['summer products', 'buying guide'],
                            'isPublished' => true,
                            'publishedAt' => '2026-06-05T00:00:00Z',
                            'blog' => [
                                'id' => 'gid://shopify/Blog/100',
                                'title' => 'News',
                                'handle' => 'news',
                            ],
                            'image' => null,
                        ],
                        'userErrors' => [],
                    ],
                ],
            ]),
        ]);

        $response = app(ShopifyService::class)->createOrUpdateArticle($blog);

        $this->assertSame('gid://shopify/Article/200', $response['article']['id']);
        $this->assertSame('https://acme.myshopify.com/blogs/news/summer-buying-guide', $response['article']['url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://acme.myshopify.com/admin/api/2026-04/graphql.json'
                && $request->hasHeader('X-Shopify-Access-Token', 'shpat_test_token')
                && Str::contains($request->data()['query'] ?? '', 'articleCreate')
                && ($request->data()['variables']['article']['blogId'] ?? null) === 'gid://shopify/Blog/100';
        });

        Http::assertNotSent(fn ($request) => Str::contains($request->url(), ['/blogs.json', '/articles.json']));
    }

    public function test_article_can_be_fetched_for_shopify_sync(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        $blog = Blog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'title' => 'Old Local Title',
            'body' => '<p>Old body.</p>',
            'shopify_article_id' => 'gid://shopify/Article/200',
            'status' => Blog::STATUS_PUBLISHED,
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'article' => [
                        'id' => 'gid://shopify/Article/200',
                        'title' => 'Updated Shopify Title',
                        'handle' => 'updated-shopify-title',
                        'body' => '<p>Updated body from Shopify.</p>',
                        'summary' => 'Updated summary.',
                        'tags' => ['seo'],
                        'isPublished' => true,
                        'publishedAt' => '2026-06-11T00:00:00Z',
                        'blog' => [
                            'id' => 'gid://shopify/Blog/100',
                            'title' => 'News',
                            'handle' => 'news',
                        ],
                        'image' => null,
                        'metafields' => [
                            'nodes' => [
                                ['key' => 'title_tag', 'value' => 'Updated SEO title', 'type' => 'single_line_text_field'],
                                ['key' => 'description_tag', 'value' => 'Updated SEO description.', 'type' => 'multi_line_text_field'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $article = app(ShopifyService::class)->getArticle($blog);

        $this->assertSame('Updated Shopify Title', $article['title']);
        $this->assertSame('<p>Updated body from Shopify.</p>', $article['body']);
        $this->assertSame('https://acme.myshopify.com/blogs/news/updated-shopify-title', $article['url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://acme.myshopify.com/admin/api/2026-04/graphql.json'
                && Str::contains($request->data()['query'] ?? '', 'GetArticle')
                && ($request->data()['variables']['id'] ?? null) === 'gid://shopify/Article/200';
        });
    }

    public function test_portal_blog_sync_resets_missing_published_blogs(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'shpat_test_token',
        ]);

        $matched = Blog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'title' => 'Matched Blog',
            'body' => '<p>Published body.</p>',
            'shopify_article_id' => 'gid://shopify/Article/200',
            'status' => Blog::STATUS_PUBLISHED,
        ]);

        $missing = Blog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'title' => 'Missing Blog',
            'body' => '<p>Should still be publishable.</p>',
            'shopify_article_id' => 'gid://shopify/Article/404',
            'status' => Blog::STATUS_PUBLISHED,
            'published_url' => 'https://acme.myshopify.com/blogs/news/missing-blog',
            'published_at' => now(),
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/api/2026-04/graphql.json' => Http::response([
                'data' => [
                    'articles' => [
                        'nodes' => [[
                            'id' => 'gid://shopify/Article/200',
                            'title' => 'Matched Blog',
                            'handle' => 'matched-blog',
                            'body' => '<p>Published body.</p>',
                            'summary' => 'Summary',
                            'tags' => ['seo'],
                            'isPublished' => true,
                            'publishedAt' => '2026-06-11T00:00:00Z',
                            'createdAt' => '2026-06-11T00:00:00Z',
                            'updatedAt' => '2026-06-11T00:00:00Z',
                            'author' => ['name' => 'Author'],
                            'blog' => [
                                'id' => 'gid://shopify/Blog/100',
                                'title' => 'News',
                                'handle' => 'news',
                            ],
                        ]],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ]),
        ]);

        $summary = app(ShopifySyncService::class)->syncPortalBlogs($store->fresh('credential'));

        $matched->refresh();
        $missing->refresh();

        $this->assertSame(['shopify_articles' => 1, 'matched' => 1, 'missing' => 1], $summary);
        $this->assertSame(Blog::STATUS_PUBLISHED, $matched->status);
        $this->assertSame('https://acme.myshopify.com/blogs/news/matched-blog', $matched->published_url);
        $this->assertSame(Blog::STATUS_APPROVED, $missing->status);
        $this->assertNull($missing->shopify_article_id);
        $this->assertNull($missing->published_url);
        $this->assertNotNull($missing->failure_message);
    }

    private function storeWithCredential(array $credential): ShopifyStore
    {
        $account = Account::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'name' => 'Acme Store',
            'shop_domain' => 'acme.myshopify.com',
            'shop_url' => 'https://acme.myshopify.com',
            'default_language' => 'en',
            'status' => 'connected',
        ]);

        $store->credential()->create([
            'account_id' => $account->id,
            ...$credential,
        ]);

        return $store;
    }
}
