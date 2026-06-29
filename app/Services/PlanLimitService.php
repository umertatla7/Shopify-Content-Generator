<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AeoGeoVisibilityReport;
use App\Models\Plan;
use App\Models\StoreAnalysis;
use App\Models\TrackedKeyword;
use App\Models\UsageLog;
use Carbon\CarbonImmutable;
use RuntimeException;

class PlanLimitService
{
    public function summary(Account|int|null $account): array
    {
        $account = $this->resolveAccount($account);
        $plan = $this->planFor($account);

        if (! $account || ! $plan) {
            return [
                'plan' => null,
                'cycle' => null,
                'metrics' => [],
            ];
        }

        $bounds = $this->monthlyBounds();

        return [
            'plan' => [
                'key' => $plan->key,
                'name' => $plan->name,
            ],
            'cycle' => [
                'starts_at' => $bounds['start']->toDateString(),
                'ends_at' => $bounds['end']->toDateString(),
            ],
            'metrics' => collect($this->definitions())
                ->mapWithKeys(fn (array $definition, string $metric) => [
                    $metric => $this->metricSummary($account, $plan, $metric, $definition, $bounds),
                ])
                ->all(),
        ];
    }

    public function ensureWithinLimit(Account|int|null $account, string $metric, int $increment = 1): void
    {
        $account = $this->resolveAccount($account);
        $plan = $this->planFor($account);

        if (! $account || ! $plan) {
            throw new RuntimeException('No customer account is selected.');
        }

        $definition = $this->definitions()[$metric] ?? null;

        if (! $definition) {
            throw new RuntimeException("Unknown plan metric [{$metric}].");
        }

        $summary = $this->metricSummary($account, $plan, $metric, $definition, $this->monthlyBounds());

        if ($summary['limit'] === null) {
            return;
        }

        if (($summary['used'] + $increment) <= $summary['limit']) {
            return;
        }

        $periodLabel = $definition['period'] === 'month' ? 'this month' : 'on your plan';

        throw new RuntimeException("Your {$plan->name} plan allows {$summary['limit']} {$definition['noun']} {$periodLabel}. You have already used {$summary['used']}.");
    }

    public function planFor(Account|int|null $account): ?Plan
    {
        $account = $this->resolveAccount($account);

        if (! $account?->plan_key) {
            return null;
        }

        return Plan::query()->where('key', $account->plan_key)->first();
    }

    private function metricSummary(Account $account, Plan $plan, string $metric, array $definition, array $bounds): array
    {
        $limitField = $definition['limit_field'];
        $limit = $plan->{$limitField};
        $used = $this->used($account, $metric, $bounds);

        return [
            'label' => $definition['label'],
            'noun' => $definition['noun'],
            'period' => $definition['period'],
            'limit' => $limit === null ? null : (int) $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, (int) $limit - $used),
        ];
    }

    private function used(Account $account, string $metric, array $bounds): int
    {
        return match ($metric) {
            'product_descriptions' => UsageLog::query()
                ->where('account_id', $account->id)
                ->where('type', 'credit_usage')
                ->where('metadata->action', 'product_content_generation')
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->count(),
            'blogs' => UsageLog::query()
                ->where('account_id', $account->id)
                ->where('type', 'credit_usage')
                ->where('metadata->action', 'blog_body_generation')
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->count(),
            'seo_reports' => StoreAnalysis::query()
                ->where('account_id', $account->id)
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->count(),
            'ai_visibility_reports' => AeoGeoVisibilityReport::query()
                ->where('account_id', $account->id)
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->count(),
            'tracked_keywords' => TrackedKeyword::query()
                ->where('account_id', $account->id)
                ->where('status', 'active')
                ->count(),
            'image_optimization' => UsageLog::query()
                ->where('account_id', $account->id)
                ->where('type', 'feature_usage')
                ->where('metadata->metric', 'image_optimization')
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->sum('quantity'),
            'image_alt_text' => UsageLog::query()
                ->where('account_id', $account->id)
                ->where('type', 'feature_usage')
                ->where('metadata->metric', 'image_alt_text')
                ->whereBetween('created_at', [$bounds['start'], $bounds['end']])
                ->sum('quantity'),
            default => 0,
        };
    }

    private function definitions(): array
    {
        return [
            'product_descriptions' => [
                'label' => 'Product descriptions',
                'noun' => 'product description generations',
                'limit_field' => 'product_description_limit',
                'period' => 'month',
            ],
            'blogs' => [
                'label' => 'Blogs',
                'noun' => 'blog generations',
                'limit_field' => 'monthly_blog_limit',
                'period' => 'month',
            ],
            'seo_reports' => [
                'label' => 'SEO reports',
                'noun' => 'SEO reports',
                'limit_field' => 'monthly_seo_report_limit',
                'period' => 'month',
            ],
            'ai_visibility_reports' => [
                'label' => 'AI visibility reports',
                'noun' => 'AI visibility reports',
                'limit_field' => 'monthly_ai_visibility_report_limit',
                'period' => 'month',
            ],
            'tracked_keywords' => [
                'label' => 'Tracked keywords',
                'noun' => 'tracked keywords',
                'limit_field' => 'tracked_keyword_limit',
                'period' => 'plan',
            ],
            'image_optimization' => [
                'label' => 'Image optimization',
                'noun' => 'image optimizations',
                'limit_field' => 'monthly_image_optimization_limit',
                'period' => 'month',
            ],
            'image_alt_text' => [
                'label' => 'Image alt text',
                'noun' => 'image alt text generations',
                'limit_field' => 'monthly_image_alt_text_limit',
                'period' => 'month',
            ],
        ];
    }

    private function monthlyBounds(): array
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        return compact('start', 'end');
    }

    private function resolveAccount(Account|int|null $account): ?Account
    {
        if ($account instanceof Account || $account === null) {
            return $account;
        }

        return Account::query()->find($account);
    }
}
