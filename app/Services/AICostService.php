<?php

namespace App\Services;

use App\Models\AIGeneration;
use Illuminate\Support\Arr;

class AICostService
{
    public function tokens(array $usage): array
    {
        $input = (int) ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));
        $cached = (int) (
            Arr::get($usage, 'prompt_tokens_details.cached_tokens')
            ?? Arr::get($usage, 'input_tokens_details.cached_tokens')
            ?? 0
        );

        return [
            'input' => $input,
            'cached_input' => min($cached, $input),
            'output' => $output,
            'total' => $total ?: ($input + $output),
        ];
    }

    public function calculate(?string $model, array $usage): float
    {
        $tokens = $this->tokens($usage);
        $rates = $this->ratesFor($model);
        $billableInput = max(0, $tokens['input'] - $tokens['cached_input']);

        return round(
            ($billableInput / 1_000_000) * $rates['input_per_million']
            + ($tokens['cached_input'] / 1_000_000) * $rates['cached_input_per_million']
            + ($tokens['output'] / 1_000_000) * $rates['output_per_million'],
            6
        );
    }

    public function costForGeneration(AIGeneration $generation): float
    {
        if ($generation->cost !== null) {
            return (float) $generation->cost;
        }

        return $this->calculate($generation->model, $generation->token_usage ?? []);
    }

    public function ratesFor(?string $model): array
    {
        $pricing = config('services.openai.pricing', []);
        $normalized = $this->normalizeModel($model ?: config('services.openai.model'));

        return $pricing[$normalized]
            ?? $pricing[$model]
            ?? [
                'input_per_million' => (float) config('services.openai.default_input_per_million', 0.40),
                'cached_input_per_million' => (float) config('services.openai.default_cached_input_per_million', 0.10),
                'output_per_million' => (float) config('services.openai.default_output_per_million', 1.60),
            ];
    }

    public function pricingContext(?string $model = null): array
    {
        $model ??= config('services.openai.model');
        $rates = $this->ratesFor($model);

        return [
            'model' => $model,
            'input_per_million' => $rates['input_per_million'],
            'cached_input_per_million' => $rates['cached_input_per_million'],
            'output_per_million' => $rates['output_per_million'],
            'source' => 'config/services.php',
        ];
    }

    public function normalizeModel(?string $model): ?string
    {
        if (! $model) {
            return null;
        }

        return preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model);
    }
}
