<?php

namespace App\Services\Future;

use LogicException;

class ContentCalendarAutomationService
{
    public function __call(string $method, array $arguments): never
    {
        throw new LogicException('Autonomous content calendar automation is reserved for Phase 4.');
    }
}
