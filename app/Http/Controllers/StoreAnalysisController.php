<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeStoreJob;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Services\PlanLimitService;
use App\Services\StoreAnalysisService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreAnalysisController extends Controller
{
    public function store(Request $request, ShopifyStore $store, StoreAnalysisService $analysis, PlanLimitService $limits): RedirectResponse
    {
        $this->authorize('create', StoreAnalysis::class);
        $this->authorize('view', $store);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['store_audit'], 403);

        try {
            $limits->ensureWithinLimit($request->user()->currentAccount, 'seo_reports');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['analysis' => $exception->getMessage()]);
        }

        if (! config('services.store_analysis.via_queue', false) || app()->environment('local') || config('queue.default') === 'sync') {
            $this->extendExecutionLimit();
            $result = $analysis->analyze($store, $request->user());

            return back()->with('status', $result->status === 'completed'
                ? 'Store analysis completed.'
                : 'Store analysis failed: '.$result->error_message);
        }

        AnalyzeStoreJob::dispatch($store->id, $request->user()->id);

        return back()->with('status', 'Store analysis queued.');
    }

    private function extendExecutionLimit(int $seconds = 120): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }
}
