<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AeoGeoVisibilityReport;
use App\Models\ShopifyStore;
use App\Services\AeoGeoVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AeoGeoVisibilityController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $this->authorizeVisibility($request);
        $account = $request->user()->currentAccount;

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
                ->with(['store:id,name,shop_domain', 'promptChecks' => fn ($query) => $query->orderBy('score')->limit(30)])
                ->latest()
                ->first()
            : null;

        return Inertia::render('Visibility/Index', [
            'stores' => $stores,
            'selectedStoreId' => $selectedStore?->id,
            'report' => $latestReport,
            'reports' => $selectedStore
                ? AeoGeoVisibilityReport::query()
                    ->forAccount($account)
                    ->where('shopify_store_id', $selectedStore->id)
                    ->latest()
                    ->limit(8)
                    ->get(['id', 'overall_score', 'aeo_score', 'geo_score', 'llm_readiness_score', 'tracked_prompt_count', 'created_at'])
                : [],
        ]);
    }

    public function store(Request $request, AeoGeoVisibilityService $visibility): RedirectResponse
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

        $report = $visibility->generate($store, $request->user());

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
}
