<?php

namespace App\Jobs;

use App\Models\BlogTopic;
use App\Models\User;
use App\Services\BlogGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $topicId, public ?int $userId = null)
    {
        $this->onQueue('ai');
    }

    public function handle(BlogGenerationService $blogs): void
    {
        $blogs->generateFromTopic(
            BlogTopic::query()->findOrFail($this->topicId),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
