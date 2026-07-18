<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasShopifyQueueDefaults;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncShopifyStoreJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasShopifyQueueDefaults;

    public int $uniqueFor = 900;

    public function __construct(public int $storeId, public ?int $syncLogId = null)
    {
        $this->onQueue('shopify');
    }

    public function handle(ShopifySyncService $sync): void
    {
        $store = ShopifyStore::query()->with('credential')->findOrFail($this->storeId);
        $log = $this->syncLogId ? ShopifySyncLog::query()->find($this->syncLogId) : null;

        $sync->syncStore($store, $log, true);
    }

    public function uniqueId(): string
    {
        return (string) $this->storeId;
    }

    public function failed(?Throwable $exception): void
    {
        if (! $this->syncLogId) {
            return;
        }

        ShopifySyncLog::query()->whereKey($this->syncLogId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?: 'Shopify sync failed after all retry attempts.',
            'completed_at' => now(),
        ]);
    }
}
