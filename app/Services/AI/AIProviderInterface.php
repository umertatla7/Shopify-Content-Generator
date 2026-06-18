<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * @return array{content: string, usage: array<string, mixed>, model: string|null, provider: string}
     */
    public function generate(string $prompt, array $options = []): array;
}
