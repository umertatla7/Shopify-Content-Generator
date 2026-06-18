<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Models\User;
use App\Services\BlogPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $blogId, public ?int $userId = null)
    {
        $this->onQueue('shopify');
    }

    public function handle(BlogPublishingService $publisher): void
    {
        $publisher->publish(
            Blog::query()->with('store.credential')->findOrFail($this->blogId),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
