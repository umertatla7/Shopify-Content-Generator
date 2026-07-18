<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasAiQueueDefaults;
use App\Models\AIGeneration;
use App\Models\Blog;
use App\Models\User;
use App\Services\BlogGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RewriteBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasAiQueueDefaults;

    public function __construct(public int $blogId, public string $instruction, public ?int $userId = null)
    {
        $this->onQueue('ai');
    }

    public function handle(BlogGenerationService $blogs): void
    {
        $blogs->rewrite(
            Blog::query()->findOrFail($this->blogId),
            $this->instruction,
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $blog = Blog::query()->find($this->blogId);

        if (! $blog) {
            return;
        }

        AIGeneration::query()
            ->where('generatable_type', $blog->getMorphClass())
            ->where('generatable_id', $blog->id)
            ->where('type', 'rewrite')
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?: 'Blog rewrite failed.',
                'completed_at' => now(),
            ]);
    }
}
