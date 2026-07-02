<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AeoGeoPromptCheck;
use App\Models\AeoGeoVisibilityReport;
use App\Models\ShopifyStore;
use App\Services\AeoGeoVisibilityService;
use App\Services\PlanLimitService;
use App\Services\UsageTrackingService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AeoGeoVisibilityController extends Controller
{
    public function index(Request $request, PlanLimitService $planLimits): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $this->authorizeVisibility($request);
        $account = $request->user()->currentAccount;

        if (! PlanFeatureGate::moduleAccess($account)['ai_visibility']) {
            return Inertia::render('FeaturePreview', PlanFeatureGate::preview('ai_visibility'));
        }

        $stores = ShopifyStore::query()
            ->forAccount($account)
            ->withCount(['products', 'collections', 'pages', 'blogs'])
            ->with('latestVisibilityReport')
            ->orderBy('name')
            ->get();

        $selectedStore = $stores->firstWhere('id', (int) $request->input('store_id')) ?? $stores->first();

        $latestReport = $selectedStore
            ? AeoGeoVisibilityReport::query()
                ->forAccount($account)
                ->where('shopify_store_id', $selectedStore->id)
                ->with(['store:id,name,shop_domain', 'promptChecks' => fn ($query) => $query->orderBy('score')])
                ->latest()
                ->first()
            : null;

        $reportHistory = $selectedStore
            ? AeoGeoVisibilityReport::query()
                ->forAccount($account)
                ->where('shopify_store_id', $selectedStore->id)
                ->latest()
                ->limit(8)
                ->get(['id', 'overall_score', 'aeo_score', 'geo_score', 'llm_readiness_score', 'prompt_coverage_score', 'tracked_prompt_count', 'created_at'])
            : collect();
        $previousReport = $reportHistory->skip(1)->first();
        $trackedKeywords = $account->trackedKeywords()
            ->when($selectedStore, fn ($query) => $query->where(function ($query) use ($selectedStore): void {
                $query->whereNull('shopify_store_id')->orWhere('shopify_store_id', $selectedStore->id);
            }))
            ->with('latestSnapshot')
            ->where('status', 'active')
            ->latest()
            ->limit(25)
            ->get();

        return Inertia::render('Visibility/Index', [
            'stores' => $stores,
            'selectedStoreId' => $selectedStore?->id,
            'report' => $latestReport,
            'technicalSignals' => $this->technicalSignals($latestReport),
            'brandPresence' => $this->brandPresence($latestReport),
            'contentOpportunities' => $this->contentOpportunities($latestReport, $this->technicalSignals($latestReport)),
            'reports' => $reportHistory,
            'trendHistory' => $this->trendHistory($reportHistory),
            'comparison' => $latestReport && $previousReport ? [
                'previous_created_at' => $previousReport->created_at,
                'overall_score_delta' => $latestReport->overall_score - $previousReport->overall_score,
                'aeo_score_delta' => $latestReport->aeo_score - $previousReport->aeo_score,
                'geo_score_delta' => $latestReport->geo_score - $previousReport->geo_score,
                'prompt_coverage_delta' => $latestReport->prompt_coverage_score - $previousReport->prompt_coverage_score,
            ] : null,
            'trackedKeywords' => $trackedKeywords,
            'planUsage' => $planLimits->summary($account),
        ]);
    }

    public function store(Request $request, AeoGeoVisibilityService $visibility, PlanLimitService $planLimits, UsageTrackingService $usage): RedirectResponse
    {
        $this->authorizeVisibility($request);
        $account = $request->user()->currentAccount;

        $validated = $request->validate([
            'shopify_store_id' => [
                'required',
                Rule::exists('shopify_stores', 'id')->where('account_id', $account->id),
            ],
        ]);

        $store = ShopifyStore::query()
            ->forAccount($account)
            ->whereKey($validated['shopify_store_id'])
            ->firstOrFail();

        $planLimits->ensureWithinLimit($account, 'ai_visibility_reports');
        $report = $visibility->generate($store, $request->user());
        $usage->record($account, 'feature_usage', 1, 'report', $report, $request->user(), [
            'metric' => 'ai_visibility_reports',
            'shopify_store_id' => $store->id,
        ]);

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
            'shopify_store_id' => $store->id,
            'subject_type' => $report->getMorphClass(),
            'subject_id' => $report->id,
            'action' => 'aeo_geo_visibility.generated',
            'entity_type' => 'visibility_report',
            'status' => 'success',
            'description' => "Generated AEO/GEO visibility report for {$store->name}.",
            'new_values' => [
                'overall_score' => $report->overall_score,
                'aeo_score' => $report->aeo_score,
                'geo_score' => $report->geo_score,
                'tracked_prompt_count' => $report->tracked_prompt_count,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('visibility.index', ['store_id' => $store->id])
            ->with('status', 'AEO/GEO visibility report generated.');
    }

    private function authorizeVisibility(Request $request): void
    {
        abort_unless($request->user()?->hasAccountPermission('stores.view'), 403);
    }

    private function technicalSignals(?AeoGeoVisibilityReport $report): array
    {
        if (! $report) {
            return [];
        }

        $snapshot = $report->source_snapshot ?? [];
        $products = $snapshot['products'] ?? [];
        $collections = $snapshot['collections'] ?? [];
        $blogs = $snapshot['blogs'] ?? [];
        $pages = $snapshot['pages'] ?? [];

        return [
            $this->signal(
                'Product content coverage',
                $products['with_descriptions'] ?? 0,
                $products['total'] ?? 0,
                'products with useful descriptions',
                'Improve product descriptions for key items so AI engines have enough source detail.',
                $report->answer_coverage_score >= 70 ? 'healthy' : ($report->answer_coverage_score >= 45 ? 'watch' : 'critical')
            ),
            $this->signal(
                'Collection landing pages',
                $collections['with_descriptions'] ?? 0,
                $collections['total'] ?? 0,
                'collections with buyer-focused copy',
                'Add intros, FAQs, and buying guidance on collection pages to strengthen prompt coverage.',
                $report->content_depth_score >= 70 ? 'healthy' : ($report->content_depth_score >= 45 ? 'watch' : 'critical')
            ),
            $this->signal(
                'Trust and answer pages',
                $pages['answer_pages'] ?? 0,
                max(3, (int) ($pages['total'] ?? 0)),
                'helpful FAQ, shipping, returns, about, and care pages',
                'Expand trust content so LLMs can cite your policies, expertise, and buying guidance.',
                ($pages['answer_pages'] ?? 0) >= 3 ? 'healthy' : (($pages['answer_pages'] ?? 0) >= 2 ? 'watch' : 'critical')
            ),
            $this->signal(
                'FAQ answer coverage',
                $blogs['with_faqs'] ?? 0,
                max(3, (int) ($blogs['portal_total'] ?? 0)),
                'blogs with FAQ sections',
                'Add direct question-and-answer blocks to existing and new blogs.',
                ($blogs['with_faqs'] ?? 0) >= 3 ? 'healthy' : (($blogs['with_faqs'] ?? 0) >= 1 ? 'watch' : 'critical')
            ),
            $this->signal(
                'Internal linking support',
                $blogs['with_internal_links'] ?? 0,
                max(5, (int) ($blogs['portal_total'] ?? 0)),
                'blogs linking to products, collections, and trust pages',
                'Strengthen internal links so AI systems can connect your supporting content to store pages.',
                ($blogs['with_internal_links'] ?? 0) >= 5 ? 'healthy' : (($blogs['with_internal_links'] ?? 0) >= 2 ? 'watch' : 'critical')
            ),
            [
                'label' => 'Structured answer readiness',
                'status' => $report->schema_readiness_score >= 70 ? 'healthy' : ($report->schema_readiness_score >= 45 ? 'watch' : 'critical'),
                'score' => (int) $report->schema_readiness_score,
                'summary' => "{$report->schema_readiness_score}/100 readiness score",
                'detail' => 'This combines FAQ-style content, SEO fields, and answer-friendly page structure from the current report.',
                'action' => 'Improve FAQ coverage, product SEO metadata, and answer-first layouts before adding more advanced schema work.',
            ],
        ];
    }

    private function brandPresence(?AeoGeoVisibilityReport $report): array
    {
        if (! $report) {
            return [
                'score' => null,
                'signals' => [],
                'summary' => null,
            ];
        }

        $signals = $report->promptChecks
            ->filter(fn (AeoGeoPromptCheck $prompt) => in_array($prompt->intent, [
                'brand_overview',
                'brand_differentiation',
                'brand_policy_clarity',
                'brand_audience_fit',
                'brand_trust',
            ], true))
            ->values()
            ->map(fn (AeoGeoPromptCheck $prompt): array => [
                'id' => $prompt->id,
                'prompt' => $prompt->prompt,
                'intent' => $prompt->intent,
                'status' => $prompt->status,
                'score' => $prompt->score,
                'recommendation' => $prompt->recommendation,
                'source_url' => $prompt->recommended_source_url,
                'target_entity_label' => $prompt->target_entity_label,
            ])
            ->all();

        if ($signals === []) {
            return [
                'score' => null,
                'signals' => [],
                'summary' => 'No brand presence prompts were generated for this report.',
            ];
        }

        $score = (int) round(collect($signals)->avg('score'));
        $covered = collect($signals)->where('status', 'covered')->count();
        $missing = collect($signals)->where('status', 'missing')->count();

        return [
            'score' => $score,
            'signals' => $signals,
            'summary' => "Brand presence is {$score}/100 with {$covered} strong branded prompts and {$missing} weak branded prompts.",
        ];
    }

    private function contentOpportunities(?AeoGeoVisibilityReport $report, array $technicalSignals): array
    {
        if (! $report) {
            return [];
        }

        $promptOpportunities = $report->promptChecks
            ->whereIn('status', ['missing', 'partial'])
            ->sortBy('score')
            ->take(4)
            ->map(function (AeoGeoPromptCheck $prompt): array {
                return [
                    'title' => $this->opportunityTitleForPrompt($prompt),
                    'priority' => $prompt->status === 'missing' ? 'high' : 'medium',
                    'source' => 'prompt_gap',
                    'detail' => $prompt->recommendation,
                    'target_label' => $prompt->target_entity_label,
                    'intent' => $prompt->intent,
                    'source_url' => $prompt->recommended_source_url,
                ];
            });

        $technicalOpportunities = collect($technicalSignals)
            ->filter(fn (array $signal) => in_array($signal['status'], ['critical', 'watch'], true))
            ->take(4)
            ->map(fn (array $signal): array => [
                'title' => $signal['label'],
                'priority' => $signal['status'] === 'critical' ? 'high' : 'medium',
                'source' => 'technical_signal',
                'detail' => $signal['action'],
                'target_label' => $signal['summary'],
                'intent' => null,
                'source_url' => null,
            ]);

        return $technicalOpportunities
            ->concat($promptOpportunities)
            ->unique('title')
            ->take(6)
            ->values()
            ->all();
    }

    private function opportunityTitleForPrompt(AeoGeoPromptCheck $prompt): string
    {
        $targetLabel = $prompt->target_entity_label ?: 'this prompt';

        return match ($prompt->intent) {
            'buying_guide' => "Create a buying guide for {$prompt->target_entity_label}",
            'product_education' => "Publish product education content for {$prompt->target_entity_label}",
            'commercial_answer' => "Strengthen commercial answer content for {$prompt->target_entity_label}",
            'brand_trust' => "Expand trust content for {$prompt->target_entity_label}",
            'brand_overview' => 'Clarify what the brand is known for',
            'brand_differentiation' => 'Explain why shoppers should buy from this brand',
            'brand_policy_clarity' => 'Tighten shipping and returns policy content',
            'brand_audience_fit' => 'Clarify who the brand is best for',
            default => "Improve content for {$targetLabel}",
        };
    }

    private function trendHistory($reportHistory): array
    {
        return $reportHistory
            ->reverse()
            ->values()
            ->map(fn (AeoGeoVisibilityReport $report): array => [
                'id' => $report->id,
                'label' => $report->created_at?->format('M j') ?? 'Unknown',
                'created_at' => $report->created_at,
                'overall_score' => $report->overall_score,
                'aeo_score' => $report->aeo_score,
                'geo_score' => $report->geo_score,
                'prompt_coverage_score' => $report->prompt_coverage_score,
            ])
            ->all();
    }

    private function signal(string $label, int $current, int $target, string $noun, string $action, string $status): array
    {
        $safeTarget = max(1, $target);
        $score = (int) round(min(100, max(0, ($current / $safeTarget) * 100)));

        return [
            'label' => $label,
            'status' => $status,
            'score' => $score,
            'summary' => "{$current}/{$target} {$noun}",
            'detail' => "Current coverage shows {$current} of {$target} {$noun}.",
            'action' => $action,
        ];
    }
}
