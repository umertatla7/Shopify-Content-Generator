<?php

namespace App\Services\Future;

use LogicException;

class ProductOptimizationService
{
    public function __call(string $method, array $arguments): never
    {
        throw new LogicException('Product optimization is reserved for Phase 2.');
    }
}
