<?php

namespace App\Services\Future;

use LogicException;

class BackgroundRemovalService
{
    public function __call(string $method, array $arguments): never
    {
        throw new LogicException('Background removal is reserved for Phase 3.');
    }
}
