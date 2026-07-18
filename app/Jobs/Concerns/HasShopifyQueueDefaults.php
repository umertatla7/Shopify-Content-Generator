<?php

namespace App\Jobs\Concerns;

trait HasShopifyQueueDefaults
{
    public int $tries = 3;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
