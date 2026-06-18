<?php

namespace App\Services\Future;

use LogicException;

class CompetitorAnalysisService
{
    public function __call(string $method, array $arguments): never
    {
        throw new LogicException('Competitor analysis is reserved for Phase 4.');
    }
}
