<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\PublishingLog;
use App\Models\User;
use App\Services\Shopify\ShopifyService;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class BlogPublishingService
{
    public function __construct(
        private readonly ShopifyService $shopify,
        private readonly UsageTrackingService $usage,
    ) {}

    public function publish(Blog $blog, ?User $user = null, bool $throwOnFailure = false): Blog
    {
        if (! $blog->canPublish()) {
            throw new RuntimeException('Only approved, scheduled, or already published blogs can be sent to Shopify.');
        }

        if (! $blog->hasPublishableBody()) {
            throw new RuntimeException('Add and save blog body content before publishing to Shopify.');
        }

        $originalStatus = $blog->status;
        $log = PublishingLog::query()->create([
            'account_id' => $blog->account_id,
            'shopify_store_id' => $blog->shopify_store_id,
            'blog_id' => $blog->id,
            'user_id' => $user?->id,
            'action' => $blog->shopify_article_id ? 'update' : 'create',
            'status' => 'running',
            'request_payload' => $blog->only(['title', 'body', 'excerpt', 'slug', 'meta_title', 'meta_description']),
        ]);

        try {
            $response = $this->shopify->createOrUpdateArticle($blog, true);
            $article = $response['article'] ?? [];
            $blogId = (string) ($article['blog_id'] ?? $blog->shopify_blog_id);
            $articleId = (string) ($article['id'] ?? $blog->shopify_article_id);
            $publishedUrl = Arr::get($article, 'url') ?: $this->publishedUrl($blog, $article);

            $blog->update([
                'status' => Blog::STATUS_PUBLISHED,
                'shopify_blog_id' => $blogId,
                'shopify_article_id' => $articleId,
                'published_url' => $publishedUrl,
                'published_at' => now(),
                'failure_message' => null,
            ]);

            $log->update([
                'status' => 'succeeded',
                'shopify_article_id' => $articleId,
                'published_url' => $publishedUrl,
                'response_payload' => $response,
            ]);

            $this->usage->record($blog->account_id, 'shopify_publish', 1, 'article', $blog, $user, [
                'shopify_store_id' => $blog->shopify_store_id,
                'shopify_article_id' => $articleId,
            ]);
        } catch (Throwable $exception) {
            $blog->update([
                'status' => $throwOnFailure ? $originalStatus : Blog::STATUS_FAILED,
                'failure_message' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }

        return $blog->refresh();
    }

    private function publishedUrl(Blog $blog, array $article): ?string
    {
        if (! $blog->store) {
            return null;
        }

        $blogHandle = Arr::get($article, 'blog.handle');
        $handle = $article['handle'] ?? $blog->slug;

        return ($blogHandle && $handle) ? rtrim($blog->store->shop_url, '/')."/blogs/{$blogHandle}/{$handle}" : null;
    }
}
