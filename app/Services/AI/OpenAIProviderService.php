<?php

namespace App\Services\AI;

use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProviderService implements AIProviderInterface
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function generate(string $prompt, array $options = []): array
    {
        $apiKey = $this->settings->get('openai_api_key', config('services.openai.api_key'));

        if (! $apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $model = $options['model'] ?? $this->settings->get('openai_model', config('services.openai.model', 'gpt-4.1-mini'));

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert Shopify SEO content strategist. Return valid JSON only when JSON is requested.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.6,
            'response_format' => ['type' => 'json_object'],
        ];

        $maxTokens = $options['max_tokens'] ?? config('services.openai.max_tokens');

        if ($maxTokens) {
            $payload['max_tokens'] = (int) $maxTokens;
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) ($options['timeout'] ?? config('services.openai.timeout', 60)))
            ->post(rtrim($this->settings->get('openai_base_url', config('services.openai.base_url', 'https://api.openai.com/v1')), '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'OpenAI request failed.');
        }

        return [
            'content' => $response->json('choices.0.message.content', '{}'),
            'usage' => $response->json('usage', []),
            'model' => $response->json('model', $model),
            'provider' => 'openai',
        ];
    }
}
