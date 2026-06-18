<?php

namespace App\Jobs;

use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\StoreAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $storeId, public ?int $userId = null)
    {
        $this->onQueue('ai');
    }

    public function handle(StoreAnalysisService $analysis): void
    {
        $analysis->analyze(
            ShopifyStore::query()->findOrFail($this->storeId),
            $this->userId ? User::query()->find($this->userId) : null,
        );
    }
}
