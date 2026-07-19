<?php

namespace App\Services\Shopify;

use App\Models\Blog;
use App\Models\ExistingShopifyBlog;
use App\Models\Product;
use App\Models\ShopifyCollection;
use App\Models\ShopifyPage;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Services\Shopify\ShopifyReconnectRequiredException;
use Illuminate\Support\Arr;
use Throwable;

class ShopifySyncService
{
    public function __construct(private readonly ShopifyService $shopify) {}

    public function syncStore(ShopifyStore $store, ?ShopifySyncLog $log = null, bool $throwOnFailure = false): ShopifySyncLog
    {
        $log ??= ShopifySyncLog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'full',
        ]);

        $log->update([
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ]);

        try {
            $metadata = $this->shopify->validateConnection($store);
            $productCount = $this->syncProducts($store);
            $collectionCount = $this->syncCollections($store);
            $pageCount = $this->syncPages($store);
            $blogCount = $this->syncExistingBlogs($store);

            $store->forceFill([
                'status' => 'connected',
                'metadata' => $metadata,
                'country' => Arr::get($metadata, 'shopAddress.countryCode', $store->country),
                'currency' => $metadata['currencyCode'] ?? $store->currency,
                'timezone' => $metadata['ianaTimezone'] ?? $store->timezone,
                'primary_locale' => $store->default_language,
                'last_validated_at' => now(),
                'last_synced_at' => now(),
                'validation_error' => null,
            ])->save();

            $log->update([
                'status' => 'completed',
                'counts' => [
                    'products' => $productCount,
                    'collections' => $collectionCount,
                    'pages' => $pageCount,
                    'existing_blogs' => $blogCount,
                ],
                'completed_at' => now(),
            ]);
        } catch (ShopifyReconnectRequiredException $exception) {
            $store->forceFill([
                'status' => 'reconnect_required',
                'validation_error' => $exception->getMessage(),
            ])->save();

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            $store->forceFill([
                'validation_error' => $exception->getMessage(),
            ])->save();

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }

        return $log->refresh();
    }

    public function syncProducts(ShopifyStore $store): int
    {
        $products = $this->shopify->paginate($store, 'products', <<<'GRAPHQL'
query SyncProducts($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    nodes {
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
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL);

        $count = 0;
        $syncedProductIds = [];

        foreach ($products as $node) {
            if ($node['id'] ?? null) {
                $syncedProductIds[] = $node['id'];
            }

            Product::query()->updateOrCreate(
                [
                    'shopify_store_id' => $store->id,
                    'shopify_product_id' => $node['id'] ?? null,
                ],
                [
                    'account_id' => $store->account_id,
                    'title' => $node['title'] ?? 'Untitled product',
                    'handle' => $node['handle'] ?? null,
                    'url' => $node['onlineStoreUrl'] ?? null,
                    'description' => $node['descriptionHtml'] ?? null,
                    'product_type' => $node['productType'] ?? null,
                    'vendor' => $node['vendor'] ?? null,
                    'status' => strtolower($node['status'] ?? ''),
                    'tags' => $node['tags'] ?? [],
                    'collections' => Arr::get($node, 'collections.nodes', []),
                    'image_url' => Arr::get($node, 'featuredImage.url'),
                    'seo_title' => Arr::get($node, 'seo.title'),
                    'seo_description' => Arr::get($node, 'seo.description'),
                    'published_at' => $node['publishedAt'] ?? null,
                    'last_synced_at' => now(),
                    'payload' => $node,
                ]
            );

            $count++;
        }

        $staleProducts = Product::query()
            ->where('shopify_store_id', $store->id)
            ->whereNotNull('shopify_product_id');

        if ($syncedProductIds === []) {
            $staleProducts->delete();
        } else {
            $staleProducts->whereNotIn('shopify_product_id', $syncedProductIds)->delete();
        }

        return $count;
    }

    public function syncCollections(ShopifyStore $store): int
    {
        $collections = $this->shopify->paginate($store, 'collections', <<<'GRAPHQL'
query SyncCollections($first: Int!, $after: String) {
  collections(first: $first, after: $after) {
    nodes {
      id
      title
      handle
      descriptionHtml
      image { url }
      seo { title description }
      ruleSet { rules { column relation condition } }
    }
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL);

        $count = 0;
        $syncedCollectionIds = [];
        $collectionProductCounts = [];

        Product::query()
            ->where('shopify_store_id', $store->id)
            ->get(['collections'])
            ->each(function (Product $product) use (&$collectionProductCounts): void {
                foreach ($product->collections ?? [] as $collection) {
                    $id = $collection['id'] ?? null;

                    if ($id) {
                        $collectionProductCounts[$id] = ($collectionProductCounts[$id] ?? 0) + 1;
                    }
                }
            });

        foreach ($collections as $node) {
            if ($node['id'] ?? null) {
                $syncedCollectionIds[] = $node['id'];
            }

            ShopifyCollection::query()->updateOrCreate(
                [
                    'shopify_store_id' => $store->id,
                    'shopify_collection_id' => $node['id'] ?? null,
                ],
                [
                    'account_id' => $store->account_id,
                    'title' => $node['title'] ?? 'Untitled collection',
                    'handle' => $node['handle'] ?? null,
                    'url' => isset($node['handle']) ? rtrim($store->shop_url, '/').'/collections/'.$node['handle'] : null,
                    'description' => $node['descriptionHtml'] ?? null,
                    'image_url' => Arr::get($node, 'image.url'),
                    'seo_title' => Arr::get($node, 'seo.title'),
                    'seo_description' => Arr::get($node, 'seo.description'),
                    'product_count' => $collectionProductCounts[$node['id'] ?? ''] ?? null,
                    'rules' => Arr::get($node, 'ruleSet.rules', []),
                    'payload' => $node,
                    'last_synced_at' => now(),
                ]
            );

            $count++;
        }

        $staleCollections = ShopifyCollection::query()
            ->where('shopify_store_id', $store->id)
            ->whereNotNull('shopify_collection_id');

        if ($syncedCollectionIds === []) {
            $staleCollections->delete();
        } else {
            $staleCollections->whereNotIn('shopify_collection_id', $syncedCollectionIds)->delete();
        }

        return $count;
    }

    public function syncExistingBlogs(ShopifyStore $store): int
    {
        return $this->syncExistingBlogsFromArticles($store, $this->fetchArticles($store));
    }

    public function syncPortalBlogs(ShopifyStore $store): array
    {
        $articles = $this->fetchArticles($store);
        $shopifyArticleCount = $this->syncExistingBlogsFromArticles($store, $articles);
        $articlesById = collect($articles)
            ->filter(fn (array $article) => filled($article['id'] ?? null))
            ->keyBy('id');

        $matched = 0;
        $missing = 0;

        Blog::query()
            ->forAccount($store->account_id)
            ->where('shopify_store_id', $store->id)
            ->where(function ($query): void {
                $query->whereNotNull('shopify_article_id')
                    ->orWhere('status', Blog::STATUS_PUBLISHED);
            })
            ->get()
            ->each(function (Blog $blog) use ($articlesById, $store, &$matched, &$missing): void {
                $article = $articlesById->get($blog->shopify_article_id);

                if ($article) {
                    $matched++;

                    $isPublished = (bool) ($article['isPublished'] ?? false);

                    $blog->update([
                        'shopify_blog_id' => Arr::get($article, 'blog.id') ?? $blog->shopify_blog_id,
                        'shopify_article_id' => $article['id'] ?? $blog->shopify_article_id,
                        'published_url' => $isPublished ? $this->shopify->articleUrl($store, $article) : null,
                        'published_at' => $isPublished ? ($article['publishedAt'] ?? $blog->published_at) : null,
                        'status' => $isPublished
                            ? Blog::STATUS_PUBLISHED
                            : ($blog->hasPublishableBody() ? Blog::STATUS_APPROVED : Blog::STATUS_DRAFT),
                        'failure_message' => null,
                    ]);

                    return;
                }

                if (! $blog->shopify_article_id && $blog->status !== Blog::STATUS_PUBLISHED) {
                    return;
                }

                $missing++;

                $blog->update([
                    'shopify_blog_id' => null,
                    'shopify_article_id' => null,
                    'published_url' => null,
                    'published_at' => null,
                    'status' => $blog->hasPublishableBody() ? Blog::STATUS_APPROVED : Blog::STATUS_DRAFT,
                    'failure_message' => 'Shopify sync could not find this article. Publish it again if you want it live.',
                ]);
            });

        return [
            'shopify_articles' => $shopifyArticleCount,
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    public function syncPages(ShopifyStore $store): int
    {
        $pages = $this->shopify->paginate($store, 'pages', <<<'GRAPHQL'
query SyncPages($first: Int!, $after: String) {
  pages(first: $first, after: $after) {
    nodes {
      id
      title
      handle
      body
      bodySummary
      isPublished
      publishedAt
      updatedAt
    }
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL);

        $count = 0;

        foreach ($pages as $page) {
            ShopifyPage::query()->updateOrCreate(
                [
                    'shopify_store_id' => $store->id,
                    'shopify_page_id' => $page['id'] ?? null,
                ],
                [
                    'account_id' => $store->account_id,
                    'title' => $page['title'] ?? 'Untitled page',
                    'handle' => $page['handle'] ?? null,
                    'url' => isset($page['handle']) ? rtrim($store->shop_url, '/').'/pages/'.$page['handle'] : null,
                    'body' => $page['body'] ?? null,
                    'summary' => $page['bodySummary'] ?? null,
                    'is_published' => (bool) ($page['isPublished'] ?? false),
                    'published_at' => $page['publishedAt'] ?? null,
                    'last_synced_at' => now(),
                    'payload' => $page,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function fetchArticles(ShopifyStore $store): array
    {
        return $this->shopify->paginate($store, 'articles', <<<'GRAPHQL'
query SyncArticles($first: Int!, $after: String) {
  articles(first: $first, after: $after) {
    nodes {
      id
      title
      handle
      body
      summary
      tags
      isPublished
      publishedAt
      createdAt
      updatedAt
      author { name }
      blog { id title handle }
    }
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL);
    }

    private function syncExistingBlogsFromArticles(ShopifyStore $store, array $articles): int
    {
        $count = 0;
        $syncedArticleIds = [];

        foreach ($articles as $article) {
            if ($article['id'] ?? null) {
                $syncedArticleIds[] = $article['id'];
            }

            ExistingShopifyBlog::query()->updateOrCreate(
                [
                    'shopify_store_id' => $store->id,
                    'shopify_article_id' => $article['id'] ?? null,
                ],
                [
                    'account_id' => $store->account_id,
                    'shopify_blog_id' => Arr::get($article, 'blog.id'),
                    'title' => $article['title'] ?? 'Untitled article',
                    'handle' => $article['handle'] ?? null,
                    'url' => $this->shopify->articleUrl($store, $article),
                    'body' => $article['body'] ?? null,
                    'excerpt' => $article['summary'] ?? null,
                    'author' => Arr::get($article, 'author.name'),
                    'tags' => $article['tags'] ?? [],
                    'published_at' => $article['publishedAt'] ?? null,
                    'last_synced_at' => now(),
                    'payload' => $article,
                ]
            );

            $count++;
        }

        $staleArticles = ExistingShopifyBlog::query()
            ->where('shopify_store_id', $store->id)
            ->whereNotNull('shopify_article_id');

        if ($syncedArticleIds === []) {
            $staleArticles->delete();
        } else {
            $staleArticles->whereNotIn('shopify_article_id', $syncedArticleIds)->delete();
        }

        return $count;
    }
}
