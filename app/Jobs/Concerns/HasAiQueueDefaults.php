<?php

namespace App\Jobs\Concerns;

trait HasAiQueueDefaults
{
    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;
}
