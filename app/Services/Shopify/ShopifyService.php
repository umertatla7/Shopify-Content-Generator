<?php

namespace App\Services\Shopify;

use App\Models\Blog;
use App\Models\Product;
use App\Models\ShopifyCollection;
use App\Models\ShopifyCredential;
use App\Models\ShopifyStore;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ShopifyService
{
    public function isValidShopDomain(string $shop): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop);
    }

    public function normalizeDomain(string $url): string
    {
        $domain = Str::of($url)
            ->replace(['https://', 'http://'], '')
            ->before('/')
            ->lower()
            ->toString();

        return trim($domain);
    }

    public function verifyRequestHmac(array $parameters, ?string $providedHmac, ?string $secret = null): bool
    {
        if (! $providedHmac) {
            return false;
        }

        unset($parameters['hmac'], $parameters['signature']);
        ksort($parameters);

        $message = collect($parameters)
            ->map(fn ($value, $key) => "{$key}=".str_replace('%', '%25', (string) $value))
            ->implode('&');

        $digest = hash_hmac('sha256', $message, $secret ?: $this->publicAppClientSecret());

        return hash_equals($digest, $providedHmac);
    }

    public function publicAppApiKey(): string
    {
        return (string) config('services.shopify.public_app_api_key');
    }

    public function publicAppClientSecret(): string
    {
        return (string) config('services.shopify.public_app_client_secret');
    }

    public function publicAppScopes(): array
    {
        return config('services.shopify.public_app_scopes', []);
    }

    public function publicAppRedirectUri(): string
    {
        return (string) (config('services.shopify.public_app_redirect_uri') ?: route('shopify.oauth.callback'));
    }

    public function authorizationUrl(string $shop, string $state): string
    {
        if (! $this->publicAppApiKey() || ! $this->publicAppClientSecret()) {
            throw new RuntimeException('Shopify public app credentials are missing. Add SHOPIFY_PUBLIC_APP_API_KEY and SHOPIFY_PUBLIC_APP_CLIENT_SECRET.');
        }

        $query = [
            'client_id' => $this->publicAppApiKey(),
            'redirect_uri' => $this->publicAppRedirectUri(),
            'state' => $state,
        ];

        $scopes = implode(',', $this->publicAppScopes());

        if ($scopes !== '') {
            $query['scope'] = $scopes;
        }

        return "https://{$shop}/admin/oauth/authorize?".http_build_query($query);
    }

    public function exchangeAuthorizationCode(string $shop, string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('services.shopify.timeout', 30))
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $this->publicAppApiKey(),
                'client_secret' => $this->publicAppClientSecret(),
                'code' => $code,
                'expiring' => 1,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error_description') ?? $response->json('error') ?? 'Shopify rejected the authorization code.');
        }

        $payload = $response->json();

        if (! ($payload['access_token'] ?? null)) {
            throw new RuntimeException('Shopify did not return an access token after OAuth.');
        }

        if (! ($payload['refresh_token'] ?? null) || (int) ($payload['expires_in'] ?? 0) <= 0) {
            throw new RuntimeException('Shopify did not return a refreshable offline access token. Restart the app installation.');
        }

        return $payload;
    }

    public function shopDetailsFromAccessToken(string $shop, string $accessToken): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ])->timeout((int) config('services.shopify.timeout', 30))
            ->post("https://{$shop}/admin/api/".config('services.shopify.api_version', '2026-04').'/graphql.json', [
                'query' => <<<'GRAPHQL'
{
  shop {
    name
    myshopifyDomain
    primaryDomain { url host }
    description
    contactEmail
    email
    currencyCode
    ianaTimezone
    shopAddress { countryCode }
    shipsToCountries
  }
}
GRAPHQL,
                'variables' => (object) [],
            ]);

        if ($response->failed() || $response->json('errors')) {
            throw new RuntimeException($response->json('errors.0.message') ?? 'Shopify returned an error while loading shop details.');
        }

        return $response->json('data.shop', []);
    }

    public function validateConnection(ShopifyStore $store): array
    {
        $response = $this->graphql($store, <<<'GRAPHQL'
{
  shop {
    name
    myshopifyDomain
    primaryDomain { url host }
    description
    contactEmail
    email
    currencyCode
    ianaTimezone
    shopAddress { countryCode }
    shipsToCountries
  }
}
GRAPHQL);

        return $response['shop'] ?? [];
    }

    public function graphql(ShopifyStore $store, string $query, array $variables = []): array
    {
        $response = $this->client($store)->post($this->baseUrl($store).'/graphql.json', [
            'query' => $query,
            'variables' => $variables ?: (object) [],
        ]);

        if ($this->isAuthenticationFailure($response->status(), $response->json('errors.0.message'))) {
            throw new ShopifyReconnectRequiredException('Shopify authorization expired. Reconnect this store from Shopify admin.');
        }

        if ($response->failed() || $response->json('errors')) {
            throw new RuntimeException($response->json('errors.0.message') ?? "Shopify Admin API request failed with status {$response->status()}.");
        }

        return $response->json('data', []);
    }

    public function paginate(ShopifyStore $store, string $connection, string $query, array $variables = [], int $pageSize = 250): array
    {
        $nodes = [];
        $after = null;

        do {
            $data = $this->graphql($store, $query, [
                ...$variables,
                'first' => $pageSize,
                'after' => $after,
            ]);

            $result = Arr::get($data, $connection, []);
            $nodes = array_merge($nodes, $result['nodes'] ?? []);
            $after = Arr::get($result, 'pageInfo.endCursor');
            $hasNextPage = (bool) Arr::get($result, 'pageInfo.hasNextPage', false);
        } while ($hasNextPage && $after);

        return $nodes;
    }

    public function createOrUpdateArticle(Blog $blog, bool $publish = true): array
    {
        $store = $blog->store()->with('credential')->firstOrFail();
        $blogId = $this->resolveBlogId($store, $blog->shopify_blog_id ?: config('services.shopify.default_blog_id'));

        if (! $blogId) {
            throw new RuntimeException('No Shopify blog was found. Sync existing blogs or configure SHOPIFY_DEFAULT_BLOG_ID.');
        }

        $article = $this->articleInput($blog, $blogId, $publish);

        if ($blog->shopify_article_id) {
            $data = $this->graphql($store, <<<'GRAPHQL'
mutation UpdateArticle($id: ID!, $article: ArticleUpdateInput!) {
  articleUpdate(id: $id, article: $article) {
    article {
      id
      title
      handle
      body
      summary
      tags
      isPublished
      publishedAt
      blog { id title handle }
      image { altText originalSrc }
    }
    userErrors { code field message }
  }
}
GRAPHQL, [
                'id' => $this->toGid($blog->shopify_article_id, 'Article'),
                'article' => [
                    ...$article,
                    'redirectNewHandle' => true,
                ],
            ]);

            $this->throwUserErrors(Arr::get($data, 'articleUpdate.userErrors', []));

            return [
                'article' => $this->normalizeArticle($store, Arr::get($data, 'articleUpdate.article', [])),
            ];
        }

        $data = $this->graphql($store, <<<'GRAPHQL'
mutation CreateArticle($article: ArticleCreateInput!) {
  articleCreate(article: $article) {
    article {
      id
      title
      handle
      body
      summary
      tags
      isPublished
      publishedAt
      blog { id title handle }
      image { altText originalSrc }
    }
    userErrors { code field message }
  }
}
GRAPHQL, [
            'article' => $article,
        ]);

        $this->throwUserErrors(Arr::get($data, 'articleCreate.userErrors', []));

        return [
            'article' => $this->normalizeArticle($store, Arr::get($data, 'articleCreate.article', [])),
        ];
    }

    public function getArticle(Blog $blog): array
    {
        $store = $blog->store()->with('credential')->firstOrFail();

        if (! $blog->shopify_article_id) {
            throw new RuntimeException('This blog does not have a Shopify article ID yet.');
        }

        $data = $this->graphql($store, <<<'GRAPHQL'
query GetArticle($id: ID!) {
  article(id: $id) {
    id
    title
    handle
    body
    summary
    tags
    isPublished
    publishedAt
    blog { id title handle }
    image { altText originalSrc }
    metafields(first: 20, namespace: "global") {
      nodes { key value type }
    }
  }
}
GRAPHQL, [
            'id' => $this->toGid($blog->shopify_article_id, 'Article'),
        ]);

        $article = Arr::get($data, 'article');

        if (! $article) {
            throw new RuntimeException('Shopify did not return this article. It may have been deleted on Shopify.');
        }

        return $this->normalizeArticle($store, $article);
    }

    public function updateProductContent(Product $product, array $content): array
    {
        $store = $product->store()->with('credential')->firstOrFail();

        if (! $product->shopify_product_id) {
            throw new RuntimeException('This product does not have a Shopify product ID.');
        }

        $data = $this->graphql($store, <<<'GRAPHQL'
mutation UpdateProductContent($product: ProductUpdateInput!) {
  productUpdate(product: $product) {
    product {
      id
      title
      handle
      descriptionHtml
      productType
      vendor
      status
      tags
      onlineStoreUrl
      featuredImage { url }
      seo { title description }
      publishedAt
      collections(first: 20) { nodes { id title handle } }
    }
    userErrors { field message }
  }
}
GRAPHQL, [
            'product' => array_filter([
                'id' => $this->toGid($product->shopify_product_id, 'Product'),
                'status' => ! empty($content['publish']) ? 'ACTIVE' : null,
                'title' => $content['title'] ?? null,
                'descriptionHtml' => $content['description_html'] ?? null,
                'seo' => array_filter([
                    'title' => $content['seo_title'] ?? null,
                    'description' => $content['seo_description'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ], fn ($value) => $value !== null && $value !== '' && $value !== []),
        ]);

        $this->throwUserErrors(Arr::get($data, 'productUpdate.userErrors', []));

        return Arr::get($data, 'productUpdate.product', []);
    }

    public function updateCollectionContent(ShopifyCollection $collection, array $content): array
    {
        $store = $collection->store()->with('credential')->firstOrFail();

        if (! $collection->shopify_collection_id) {
            throw new RuntimeException('This collection does not have a Shopify collection ID.');
        }

        $data = $this->graphql($store, <<<'GRAPHQL'
mutation UpdateCollectionContent($input: CollectionInput!) {
  collectionUpdate(input: $input) {
    collection {
      id
      title
      handle
      descriptionHtml
      image { url }
      seo { title description }
      updatedAt
    }
    userErrors { field message }
  }
}
GRAPHQL, [
            'input' => array_filter([
                'id' => $this->toGid($collection->shopify_collection_id, 'Collection'),
                'descriptionHtml' => $content['description_html'] ?? null,
                'handle' => $content['handle'] ?? null,
                'redirectNewHandle' => true,
                'seo' => array_filter([
                    'title' => $content['seo_title'] ?? null,
                    'description' => $content['seo_description'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ], fn ($value) => $value !== null && $value !== '' && $value !== []),
        ]);

        $this->throwUserErrors(Arr::get($data, 'collectionUpdate.userErrors', []));

        return Arr::get($data, 'collectionUpdate.collection', []);
    }

    public function articleUrl(ShopifyStore $store, array $article): ?string
    {
        $blogHandle = Arr::get($article, 'blog.handle');
        $articleHandle = $article['handle'] ?? null;

        if (! $blogHandle || ! $articleHandle) {
            return null;
        }

        return rtrim($store->shop_url, '/')."/blogs/{$blogHandle}/{$articleHandle}";
    }

    public function toGid(string $id, string $resource): string
    {
        if (str_starts_with($id, 'gid://shopify/')) {
            return $id;
        }

        return "gid://shopify/{$resource}/{$id}";
    }

    public function ensureAccessToken(ShopifyStore $store): string
    {
        return DB::transaction(function () use ($store): string {
            $credential = ShopifyCredential::query()
                ->where('shopify_store_id', $store->id)
                ->lockForUpdate()
                ->first();

            if (! $credential) {
                throw new ShopifyReconnectRequiredException('Shopify credentials are missing. Reconnect this store from Shopify admin.');
            }

            if (
                $credential->admin_api_access_token
                && (
                    ! $credential->expires_at
                    || $credential->expires_at->gt(now()->addMinutes(10))
                )
            ) {
                return $credential->admin_api_access_token;
            }

            if (
                ! $credential->refresh_token
                || ($credential->refresh_token_expires_at && $credential->refresh_token_expires_at->isPast())
            ) {
                throw new ShopifyReconnectRequiredException('Shopify authorization expired. Reconnect this store from Shopify admin.');
            }

            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.shopify.timeout', 30))
                ->post("https://{$store->shop_domain}/admin/oauth/access_token", [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->publicAppApiKey(),
                    'client_secret' => $this->publicAppClientSecret(),
                    'refresh_token' => $credential->refresh_token,
                ]);

            if ($response->status() === 401) {
                throw new ShopifyReconnectRequiredException('Shopify authorization expired. Reconnect this store from Shopify admin.');
            }

            if ($response->failed()) {
                throw new RuntimeException('Shopify token refresh is temporarily unavailable. Try again shortly.');
            }

            $token = (string) $response->json('access_token', '');
            $refreshToken = (string) $response->json('refresh_token', '');
            $expiresIn = (int) $response->json('expires_in', 0);
            $refreshExpiresIn = (int) $response->json('refresh_token_expires_in', 0);

            if ($token === '' || $refreshToken === '' || $expiresIn <= 0) {
                throw new RuntimeException('Shopify returned an incomplete token refresh response. Try reconnecting the store if this continues.');
            }

            $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $response->json('scope', '')))));

            $credential->forceFill([
                'admin_api_access_token' => $token,
                'refresh_token' => $refreshToken,
                'scopes' => $scopes ?: $credential->scopes,
                'expires_at' => now()->addSeconds($expiresIn),
                'refresh_token_expires_at' => $refreshExpiresIn > 0
                    ? now()->addSeconds($refreshExpiresIn)
                    : $credential->refresh_token_expires_at,
            ])->save();

            $store->setRelation('credential', $credential);

            return $token;
        }, 3);
    }

    private function client(ShopifyStore $store): PendingRequest
    {
        $token = $this->ensureAccessToken($store);

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->timeout((int) config('services.shopify.timeout', 30));
    }

    private function baseUrl(ShopifyStore $store): string
    {
        $version = config('services.shopify.api_version', '2026-04');

        return "https://{$store->shop_domain}/admin/api/{$version}";
    }

    private function isAuthenticationFailure(int $status, mixed $message): bool
    {
        if ($status === 401) {
            return true;
        }

        $message = is_string($message) ? Str::lower($message) : '';

        return Str::contains($message, [
            'non-expiring access tokens are no longer accepted',
            'invalid access token',
            'access token is invalid',
        ]);
    }

    private function resolveBlogId(ShopifyStore $store, ?string $blogId): ?string
    {
        if ($blogId) {
            return $this->toGid($blogId, 'Blog');
        }

        $data = $this->graphql($store, <<<'GRAPHQL'
{
  blogs(first: 1) {
    nodes { id title handle }
  }
}
GRAPHQL);

        return Arr::get($data, 'blogs.nodes.0.id');
    }

    private function articleInput(Blog $blog, string $blogId, bool $publish): array
    {
        $metafields = array_filter([
            $this->metafield('title_tag', $blog->meta_title ?: $blog->seo_title ?: $blog->title, 'single_line_text_field'),
            $this->metafield('description_tag', $blog->meta_description, 'multi_line_text_field'),
        ]);

        $article = array_filter([
            'blogId' => $blogId,
            'title' => $blog->title,
            'author' => ['name' => config('app.name', 'SEO & AEO Content Generator')],
            'handle' => $blog->slug,
            'body' => $blog->body,
            'summary' => $blog->excerpt,
            'isPublished' => $publish,
            'publishDate' => $publish ? now()->toIso8601String() : null,
            'tags' => $this->articleTags($blog),
            'metafields' => $metafields ? array_values($metafields) : null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($blog->featured_image_url) {
            $article['image'] = array_filter([
                'url' => $blog->featured_image_url,
                'altText' => $blog->featured_image_alt ?: $blog->title,
            ]);
        }

        return $article;
    }

    private function metafield(string $key, ?string $value, string $type): ?array
    {
        if (! $value) {
            return null;
        }

        return [
            'namespace' => 'global',
            'key' => $key,
            'type' => $type,
            'value' => $value,
        ];
    }

    private function articleTags(Blog $blog): array
    {
        return array_values(array_unique(array_filter([
            $blog->primary_keyword,
            ...($blog->secondary_keywords ?? []),
        ])));
    }

    private function normalizeArticle(ShopifyStore $store, array $article): array
    {
        return [
            ...$article,
            'blog_id' => Arr::get($article, 'blog.id'),
            'url' => $this->articleUrl($store, $article),
        ];
    }

    private function throwUserErrors(array $errors): void
    {
        if (! $errors) {
            return;
        }

        $messages = array_map(
            fn (array $error) => trim(implode(' ', array_filter([$error['code'] ?? null, $error['message'] ?? null]))),
            $errors
        );

        throw new RuntimeException(implode('; ', array_filter($messages)));
    }
}
