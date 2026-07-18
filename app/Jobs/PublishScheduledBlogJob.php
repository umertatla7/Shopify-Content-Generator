<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasShopifyQueueDefaults;
use App\Models\Blog;
use App\Models\BlogSchedule;
use App\Services\BlogPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class PublishScheduledBlogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasShopifyQueueDefaults;

    public int $uniqueFor = 600;

    public function __construct(public int $scheduleId)
    {
        $this->onQueue('shopify');
    }

    public function handle(BlogPublishingService $publisher): void
    {
        $schedule = BlogSchedule::query()
            ->with('blog.store.credential')
            ->findOrFail($this->scheduleId);

        if ($schedule->status === 'completed') {
            return;
        }

        if ($schedule->status !== 'running') {
            throw new RuntimeException('This schedule has not been claimed for publishing.');
        }

        $blog = $schedule->blog;

        if (! $blog || ! in_array($blog->status, [Blog::STATUS_APPROVED, Blog::STATUS_SCHEDULED, Blog::STATUS_PUBLISHED], true)) {
            throw new RuntimeException('Blog is not approved for publishing.');
        }

        $schedule->update(['last_attempt_at' => now()]);
        $publisher->publish($blog, null, true);

        $schedule->update([
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $schedule = BlogSchedule::query()->with('blog')->find($this->scheduleId);

        if (! $schedule || $schedule->status === 'completed') {
            return;
        }

        $message = $exception?->getMessage() ?: 'Scheduled publishing failed.';
        $schedule->update([
            'status' => 'failed',
            'error_message' => $message,
            'last_attempt_at' => now(),
        ]);

        if ($schedule->blog && $schedule->blog->status !== Blog::STATUS_PUBLISHED) {
            $schedule->blog->update([
                'status' => Blog::STATUS_FAILED,
                'failure_message' => $message,
            ]);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->scheduleId;
    }
}
