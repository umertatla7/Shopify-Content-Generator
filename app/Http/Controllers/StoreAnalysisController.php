<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeStoreJob;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Services\StoreAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreAnalysisController extends Controller
{
    public function store(Request $request, ShopifyStore $store, StoreAnalysisService $analysis): RedirectResponse
    {
        $this->authorize('create', StoreAnalysis::class);
        $this->authorize('view', $store);

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
