<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasShopifyQueueDefaults;
use App\Models\Blog;
use App\Models\User;
use App\Services\BlogPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PublishBlogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasShopifyQueueDefaults;

    public int $uniqueFor = 600;

    public function __construct(public int $blogId, public ?int $userId = null)
    {
        $this->onQueue('shopify');
    }

    public function handle(BlogPublishingService $publisher): void
    {
        $publisher->publish(
            Blog::query()->with('store.credential')->findOrFail($this->blogId),
            $this->userId ? User::query()->find($this->userId) : null,
            true,
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->blogId;
    }

    public function failed(?Throwable $exception): void
    {
        Blog::query()->whereKey($this->blogId)->where('status', '!=', Blog::STATUS_PUBLISHED)->update([
            'status' => Blog::STATUS_FAILED,
            'failure_message' => $exception?->getMessage() ?: 'Publishing failed after all retry attempts.',
        ]);
    }
}
