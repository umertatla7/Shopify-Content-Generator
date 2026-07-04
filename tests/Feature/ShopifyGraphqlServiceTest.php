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

    public function test_expired_token_is_refreshed_from_client_credentials_before_graphql_request(): void
    {
        $store = $this->storeWithCredential([
            'admin_api_access_token' => 'old_token',
            'api_key' => 'client_id',
            'client_secret' => 'client_secret',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            'https://acme.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'new_token',
                'scope' => 'read_products,write_content',
                'expires_in' => 86399,
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
        $this->assertSame(['read_products', 'write_content'], $credential->scopes);
        $this->assertTrue($credential->expires_at->isFuture());

        Http::assertSent(fn ($request) => $request->url() === 'https://acme.myshopify.com/admin/oauth/access_token'
            && ($request->data()['grant_type'] ?? null) === 'client_credentials'
            && ($request->data()['client_id'] ?? null) === 'client_id');

        Http::assertSent(fn ($request) => $request->url() === 'https://acme.myshopify.com/admin/api/2026-04/graphql.json'
            && $request->hasHeader('X-Shopify-Access-Token', 'new_token'));
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
