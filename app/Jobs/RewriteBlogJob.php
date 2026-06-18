<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Models\User;
use App\Services\BlogGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RewriteBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
}
