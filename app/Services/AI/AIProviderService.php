<?php

namespace App\Services\AI;

class AIProviderService
{
    public function __construct(private readonly AIProviderInterface $provider) {}

    /**
     * @return array{content: string, usage: array<string, mixed>, model: string|null, provider: string}
     */
    public function generate(string $prompt, array $options = []): array
    {
        return $this->provider->generate($prompt, $options);
    }

    public function decodeJson(string $content, array $fallback = []): array
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : $fallback;
    }
}
