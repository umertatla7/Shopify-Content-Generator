<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Plan;

class PlanFeatureGate
{
    public static function featureOptions(): array
    {
        return [
            ['key' => 'product_descriptions', 'label' => 'Product content generation', 'description' => 'Generate and push Shopify product titles, descriptions, and SEO fields.'],
            ['key' => 'collection_descriptions', 'label' => 'Collection content generation', 'description' => 'Generate collection descriptions and SEO content.'],
            ['key' => 'monthly_blog_generation', 'label' => 'Topics and blog drafts', 'description' => 'Generate topic ideas, approve topics, and create blog drafts.'],
            ['key' => 'store_audit', 'label' => 'Store Audit', 'description' => 'Access synced store audit, performance, content, and technical readiness.'],
            ['key' => 'seo_reports', 'label' => 'AI Store Analysis', 'description' => 'Run and review store analysis reports and SEO opportunities.'],
            ['key' => 'rank_tracking', 'label' => 'Keyword Tracking', 'description' => 'Use Search Console mapping and tracked keyword monitoring.'],
            ['key' => 'ai_visibility', 'label' => 'AI Visibility', 'description' => 'Run prompt coverage, brand presence, and AI visibility reports.'],
            ['key' => 'image_optimization', 'label' => 'Image optimization', 'description' => 'Optimize Shopify media within the plan allowance.'],
            ['key' => 'image_alt_text', 'label' => 'Image alt text', 'description' => 'Generate image alt text within the plan allowance.'],
            ['key' => 'all_features', 'label' => 'All features', 'description' => 'Unlock every gated feature in the workspace.'],
        ];
    }

    public static function moduleAccess(?Account $account): array
    {
        return [
            'dashboard' => true,
            'billing' => true,
            'products' => self::allows($account, 'product_descriptions'),
            'collections' => self::allows($account, 'collection_descriptions'),
            'topics' => self::allows($account, 'monthly_blog_generation'),
            'blogs' => self::allows($account, 'monthly_blog_generation'),
            'schedule' => self::allows($account, 'monthly_blog_generation'),
            'store_audit' => self::allowsAny($account, ['store_audit', 'basic_store_audit', 'seo_reports']),
            'rank_tracking' => self::allows($account, 'rank_tracking'),
            'ai_visibility' => self::allows($account, 'ai_visibility'),
        ];
    }

    public static function preview(string $module): array
    {
        return match ($module) {
            'store_audit' => [
                'title' => 'Store Audit',
                'description' => 'Unlock technical, content, and storefront audit data for the connected Shopify store.',
                'highlights' => ['Performance scorecards', 'Content gap findings', 'SEO and answer-engine checks'],
                'metrics' => [
                    ['label' => 'Performance', 'value' => '88/100'],
                    ['label' => 'Collections', 'value' => '12 tracked'],
                    ['label' => 'Content gaps', 'value' => '7 found'],
                ],
            ],
            'rank_tracking' => [
                'title' => 'Keyword Tracking',
                'description' => 'Track keyword positions, Search Console properties, and ranking momentum after you upgrade.',
                'highlights' => ['Tracked keyword slots', 'Property mapping', 'Page and query performance'],
                'metrics' => [
                    ['label' => 'Tracked keywords', 'value' => '25'],
                    ['label' => 'Avg position', 'value' => '11.8'],
                    ['label' => 'Top page CTR', 'value' => '4.6%'],
                ],
            ],
            'ai_visibility' => [
                'title' => 'AI Visibility',
                'description' => 'See how answer engines and LLM-style prompts understand the store after you upgrade.',
                'highlights' => ['Prompt coverage', 'Brand presence', 'Trend history and comparison'],
                'metrics' => [
                    ['label' => 'Overall score', 'value' => '74'],
                    ['label' => 'Prompt coverage', 'value' => '68'],
                    ['label' => 'Brand presence', 'value' => '71'],
                ],
            ],
            default => [
                'title' => 'Feature preview',
                'description' => 'Upgrade the package to unlock this module.',
                'highlights' => [],
                'metrics' => [],
            ],
        };
    }

    public static function allows(?Account $account, string $feature): bool
    {
        if (! $account) {
            return false;
        }

        $features = self::features($account);

        return in_array('all_features', $features, true) || in_array($feature, $features, true);
    }

    public static function allowsAny(?Account $account, array $features): bool
    {
        foreach ($features as $feature) {
            if (self::allows($account, $feature)) {
                return true;
            }
        }

        return false;
    }

    public static function features(?Account $account): array
    {
        if (! $account) {
            return [];
        }

        $plan = Plan::query()->where('key', $account->plan_key)->first();

        return array_values(array_filter($plan?->features ?? []));
    }
}
