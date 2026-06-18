<?php

namespace App\Jobs;

use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncShopifyStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $storeId, public ?int $syncLogId = null)
    {
        $this->onQueue('shopify');
    }

    public function handle(ShopifySyncService $sync): void
    {
        $store = ShopifyStore::query()->with('credential')->findOrFail($this->storeId);
        $log = $this->syncLogId ? ShopifySyncLog::query()->find($this->syncLogId) : null;

        $sync->syncStore($store, $log);
    }
}
