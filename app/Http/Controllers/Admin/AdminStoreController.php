<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIGeneration;
use App\Models\ActivityLog;
use App\Models\PublishingLog;
use App\Models\ShopifyStore;
use App\Models\UsageLog;
use App\Services\AICostService;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/Stores/Index', [
            'filters' => $filters,
            'stores' => ShopifyStore::query()
                ->with('account:id,name,plan_key,credit_balance')
                ->withCount(['products', 'collections', 'blogs'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('shop_domain', 'like', "%{$search}%");
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, ShopifyStore $store, AICostService $costs): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $store->load([
            'account:id,name,plan_key,credit_balance,monthly_credit_allowance,status',
            'credential:id,shopify_store_id,expires_at,scopes,created_at,updated_at',
            'latestSyncLog',
            'latestAnalysis',
            'latestVisibilityReport',
        ])->loadCount(['products', 'collections', 'pages', 'blogs', 'existingBlogs', 'visibilityReports']);

        $generations = AIGeneration::query()
            ->with(['user:id,name,email'])
            ->where('shopify_store_id', $store->id)
            ->latest()
            ->get(['id', 'account_id', 'shopify_store_id', 'user_id', 'provider', 'model', 'type', 'status', 'token_usage', 'cost', 'created_at']);
        $monthlyGenerations = $generations->filter(fn (AIGeneration $generation) => $generation->created_at?->gte(now()->startOfMonth()));
        $creditUsageQuery = UsageLog::query()
            ->with('user:id,name,email')
            ->where('shopify_store_id', $store->id)
            ->where('type', 'credit_usage');

        return Inertia::render('Admin/Stores/Show', [
            'store' => $store,
            'aiCostSummary' => [
                'all_time' => $this->summarizeGenerations($generations, $costs),
                'current_month' => $this->summarizeGenerations($monthlyGenerations, $costs),
            ],
            'creditsUsed' => [
                'all_time' => (int) (clone $creditUsageQuery)->sum('quantity'),
                'current_month' => (int) (clone $creditUsageQuery)->where('created_at', '>=', now()->startOfMonth())->sum('quantity'),
                'recent' => (clone $creditUsageQuery)->latest()->limit(20)->get(['id', 'user_id', 'quantity', 'unit', 'metadata', 'created_at']),
            ],
            'recentFailures' => [
                'sync' => $store->syncLogs()->where('status', 'failed')->latest()->limit(10)->get(['id', 'sync_type', 'status', 'counts', 'error_message', 'started_at', 'completed_at', 'created_at']),
                'publish' => PublishingLog::query()->with('blog:id,title')->where('shopify_store_id', $store->id)->where('status', 'failed')->latest()->limit(10)->get(['id', 'blog_id', 'action', 'status', 'error_message', 'created_at']),
                'analysis' => $store->analyses()->where('status', 'failed')->latest()->limit(10)->get(['id', 'status', 'error_message', 'created_at', 'completed_at']),
            ],
            'activity' => ActivityLog::query()
                ->with(['user:id,name,email', 'account:id,name'])
                ->where('shopify_store_id', $store->id)
                ->latest()
                ->limit(25)
                ->get(),
            'recentGenerations' => $generations->take(20)->values(),
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
}
