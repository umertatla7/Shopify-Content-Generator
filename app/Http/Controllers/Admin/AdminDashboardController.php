<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\AIGeneration;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PublishingLog;
use App\Models\ShopifyStore;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\AICostService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AICostService $costs): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $generations = AIGeneration::query()
            ->with(['account:id,name,plan_key', 'store:id,name,account_id', 'user:id,name,email'])
            ->whereNotNull('token_usage')
            ->latest()
            ->get(['id', 'account_id', 'shopify_store_id', 'user_id', 'provider', 'model', 'type', 'status', 'token_usage', 'cost', 'created_at']);
        $monthlyGenerations = $generations->filter(fn (AIGeneration $generation) => $generation->created_at?->gte(now()->startOfMonth()));

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'accounts' => Account::query()->count(),
                'users' => User::query()->count(),
                'stores' => ShopifyStore::query()->count(),
                'products' => Product::query()->count(),
                'topics' => BlogTopic::query()->count(),
                'blogs' => Blog::query()->count(),
                'published' => Blog::query()->where('status', Blog::STATUS_PUBLISHED)->count(),
                'failed' => Blog::query()->where('status', Blog::STATUS_FAILED)->count(),
                'ai_generations' => AIGeneration::query()->count(),
                'shopify_publishes' => PublishingLog::query()->where('status', 'succeeded')->count(),
                'usage_events' => UsageLog::query()->count(),
            ],
            'aiPricing' => $costs->pricingContext(),
            'aiCostSummary' => [
                'all_time' => $this->summarizeGenerations($generations, $costs),
                'current_month' => $this->summarizeGenerations($monthlyGenerations, $costs),
            ],
            'accountCosts' => $this->accountCosts($monthlyGenerations, $costs),
            'storeCosts' => $this->storeCosts($monthlyGenerations, $costs),
            'userCosts' => $this->userCosts($monthlyGenerations, $costs),
            'generationTypeCosts' => $this->typeCosts($generations, $costs),
            'planFormula' => $this->planFormula($generations, $costs),
            'blogStatusCounts' => Blog::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'accounts' => Account::query()
                ->withCount(['users', 'stores', 'blogs'])
                ->latest()
                ->limit(8)
                ->get(['id', 'name', 'slug', 'plan_key', 'created_at']),
            'users' => User::query()
                ->with('currentAccount:id,name')
                ->latest()
                ->limit(8)
                ->get(['id', 'current_account_id', 'name', 'email', 'global_role', 'created_at']),
            'activity' => ActivityLog::query()
                ->with(['user:id,name,email', 'account:id,name'])
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    private function summarizeGenerations(Collection $generations, AICostService $costs): array
    {
        return $generations->reduce(function (array $carry, AIGeneration $generation) use ($costs): array {
            $tokens = $costs->tokens($generation->token_usage ?? []);

            return [
                'generations' => $carry['generations'] + 1,
                'input_tokens' => $carry['input_tokens'] + $tokens['input'],
                'cached_input_tokens' => $carry['cached_input_tokens'] + $tokens['cached_input'],
                'output_tokens' => $carry['output_tokens'] + $tokens['output'],
                'total_tokens' => $carry['total_tokens'] + $tokens['total'],
                'estimated_cost' => round($carry['estimated_cost'] + $costs->costForGeneration($generation), 4),
            ];
        }, [
            'generations' => 0,
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost' => 0.0,
        ]);
    }

    private function accountCosts(Collection $generations, AICostService $costs): array
    {
        $accounts = Account::query()
            ->withCount(['stores', 'users'])
            ->get(['id', 'name', 'plan_key'])
            ->keyBy('id');
        $planPrices = Plan::query()->pluck('monthly_price', 'key');

        return $generations
            ->groupBy('account_id')
            ->map(function (Collection $rows, int|string $accountId) use ($accounts, $planPrices, $costs): array {
                $account = $accounts->get((int) $accountId);
                $summary = $this->summarizeGenerations($rows, $costs);
                $revenue = (float) ($planPrices[$account?->plan_key] ?? 0);

                return [
                    'id' => (int) $accountId,
                    'name' => $account?->name ?? 'Unknown account',
                    'plan_key' => $account?->plan_key ?? '-',
                    'monthly_revenue' => $revenue,
                    'estimated_cost' => $summary['estimated_cost'],
                    'estimated_profit' => round($revenue - $summary['estimated_cost'], 2),
                    'gross_margin' => $revenue > 0 ? round((($revenue - $summary['estimated_cost']) / $revenue) * 100, 1) : null,
                    'stores_count' => $account?->stores_count ?? 0,
                    'users_count' => $account?->users_count ?? 0,
                    ...$summary,
                ];
            })
            ->sortByDesc('estimated_cost')
            ->values()
            ->take(10)
            ->all();
    }

    private function storeCosts(Collection $generations, AICostService $costs): array
    {
        return $generations
            ->whereNotNull('shopify_store_id')
            ->groupBy('shopify_store_id')
            ->map(function (Collection $rows) use ($costs): array {
                /** @var AIGeneration $first */
                $first = $rows->first();
                $summary = $this->summarizeGenerations($rows, $costs);

                return [
                    'id' => $first->shopify_store_id,
                    'name' => $first->store?->name ?? 'Unknown store',
                    'account' => $first->account?->name ?? 'Unknown account',
                    ...$summary,
                ];
            })
            ->sortByDesc('estimated_cost')
            ->values()
            ->take(10)
            ->all();
    }

    private function userCosts(Collection $generations, AICostService $costs): array
    {
        return $generations
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(function (Collection $rows) use ($costs): array {
                /** @var AIGeneration $first */
                $first = $rows->first();
                $summary = $this->summarizeGenerations($rows, $costs);

                return [
                    'id' => $first->user_id,
                    'name' => $first->user?->name ?? 'Unknown user',
                    'email' => $first->user?->email ?? '-',
                    'account' => $first->account?->name ?? 'Unknown account',
                    ...$summary,
                ];
            })
            ->sortByDesc('estimated_cost')
            ->values()
            ->take(10)
            ->all();
    }

    private function typeCosts(Collection $generations, AICostService $costs): array
    {
        return $generations
            ->groupBy('type')
            ->map(function (Collection $rows, string $type) use ($costs): array {
                $summary = $this->summarizeGenerations($rows, $costs);

                return [
                    'type' => $type,
                    'average_cost' => $summary['generations'] > 0 ? round($summary['estimated_cost'] / $summary['generations'], 4) : 0,
                    'average_tokens' => $summary['generations'] > 0 ? (int) round($summary['total_tokens'] / $summary['generations']) : 0,
                    ...$summary,
                ];
            })
            ->sortByDesc('estimated_cost')
            ->values()
            ->all();
    }

    private function planFormula(Collection $generations, AICostService $costs): array
    {
        $typeCosts = collect($this->typeCosts($generations, $costs))->keyBy('type');
        $blogUnit = $this->averageOrFallback($typeCosts, 'blog_generation', $costs)
            + $this->averageOrFallback($typeCosts, 'blog_body_generation', $costs);
        $productUnit = $this->averageOrFallback($typeCosts, 'product_content_generation', $costs);
        $collectionUnit = $this->averageOrFallback($typeCosts, 'collection_content_generation', $costs);
        $scenario = [
            'blogs' => 10,
            'products' => 50,
            'collections' => 10,
            'target_price' => 19.00,
            'safety_multiplier' => 3,
            'target_margin' => 80,
        ];
        $rawCost = ($scenario['blogs'] * $blogUnit)
            + ($scenario['products'] * $productUnit)
            + ($scenario['collections'] * $collectionUnit);
        $bufferedCost = $rawCost * $scenario['safety_multiplier'];
        $targetPrice = $scenario['target_price'];
        $profit = $targetPrice - $bufferedCost;

        return [
            ...$scenario,
            'unit_costs' => [
                'blog' => round($blogUnit, 4),
                'product_description' => round($productUnit, 4),
                'collection_description' => round($collectionUnit, 4),
            ],
            'estimated_raw_ai_cost' => round($rawCost, 2),
            'estimated_buffered_ai_cost' => round($bufferedCost, 2),
            'estimated_profit_at_target_price' => round($profit, 2),
            'estimated_margin_at_target_price' => $targetPrice > 0 ? round(($profit / $targetPrice) * 100, 1) : null,
            'suggested_price_for_target_margin' => round($bufferedCost / (1 - ($scenario['target_margin'] / 100)), 2),
        ];
    }

    private function averageOrFallback(Collection $typeCosts, string $type, AICostService $costs): float
    {
        $average = (float) ($typeCosts->get($type)['average_cost'] ?? 0);

        if ($average > 0) {
            return $average;
        }

        return match ($type) {
            'blog_generation' => $costs->calculate(config('services.openai.model'), ['prompt_tokens' => 3500, 'completion_tokens' => 1200]),
            'blog_body_generation' => $costs->calculate(config('services.openai.model'), ['prompt_tokens' => 6500, 'completion_tokens' => 2500]),
            'product_content_generation' => $costs->calculate(config('services.openai.model'), ['prompt_tokens' => 2500, 'completion_tokens' => 700]),
            'collection_content_generation' => $costs->calculate(config('services.openai.model'), ['prompt_tokens' => 3200, 'completion_tokens' => 900]),
            default => 0.0,
        };
    }
}
