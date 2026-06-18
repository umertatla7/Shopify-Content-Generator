<?php

namespace App\Services\Future;

use LogicException;

class ImageGenerationService
{
    public function __call(string $method, array $arguments): never
    {
        throw new LogicException('AI image generation is reserved for Phase 3.');
    }
}
