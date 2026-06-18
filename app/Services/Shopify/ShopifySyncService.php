<?php

namespace App\Services\Shopify;

use App\Models\ExistingShopifyBlog;
use App\Models\Product;
use App\Models\ShopifyCollection;
use App\Models\ShopifyPage;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use Illuminate\Support\Arr;
use Throwable;

class ShopifySyncService
{
    public function __construct(private readonly ShopifyService $shopify) {}

    public function syncStore(ShopifyStore $store, ?ShopifySyncLog $log = null): ShopifySyncLog
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
        } catch (Throwable $exception) {
            $store->forceFill([
                'status' => 'disconnected',
                'validation_error' => $exception->getMessage(),
            ])->save();

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
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
        $articles = $this->shopify->paginate($store, 'articles', <<<'GRAPHQL'
query SyncArticles($first: Int!, $after: String) {
  articles(first: $first, after: $after) {
    nodes {
      id
      title
      handle
      body
      summary
      tags
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

        $count = 0;

        foreach ($articles as $article) {
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

        return $count;
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
}
