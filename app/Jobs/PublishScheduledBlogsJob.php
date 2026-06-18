<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Models\BlogSchedule;
use App\Services\BlogPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledBlogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('shopify');
    }

    public function handle(BlogPublishingService $publisher): void
    {
        BlogSchedule::query()
            ->with('blog.store.credential')
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->chunkById(50, function ($schedules) use ($publisher): void {
                foreach ($schedules as $schedule) {
                    $schedule->update([
                        'status' => 'running',
                        'last_attempt_at' => now(),
                    ]);

                    $blog = $schedule->blog;

                    if (! $blog || ! in_array($blog->status, [Blog::STATUS_APPROVED, Blog::STATUS_SCHEDULED], true)) {
                        $schedule->update([
                            'status' => 'failed',
                            'error_message' => 'Blog is not approved for publishing.',
                        ]);

                        continue;
                    }

                    $publisher->publish($blog);
                    $schedule->update([
                        'status' => $blog->fresh()->status === Blog::STATUS_PUBLISHED ? 'completed' : 'failed',
                    ]);
                }
            });
    }
}
