<?php

namespace App\Jobs;

use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\BlogTopicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBlogTopicsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $storeId, public array $options = [], public ?int $userId = null)
    {
        $this->onQueue('ai');
    }

    public function handle(BlogTopicService $topics): void
    {
        $topics->generate(
            ShopifyStore::query()->findOrFail($this->storeId),
            $this->options,
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
