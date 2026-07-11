<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, CreditService $credits): Response
    {
        $account = $request->user()->currentAccount;
        $accountId = $account?->id;

        $primaryStore = $accountId
            ? ShopifyStore::query()
                ->forAccount($accountId)
                ->where('status', 'connected')
                ->withCount(['products', 'collections', 'pages', 'existingBlogs'])
                ->with(['latestSyncLog:shopify_sync_logs.id,shopify_sync_logs.shopify_store_id,shopify_sync_logs.status,shopify_sync_logs.completed_at,shopify_sync_logs.started_at,shopify_sync_logs.error_message'])
                ->latest('id')
                ->first()
            : null;

        $hasSyncedCatalog = $primaryStore
            ? (($primaryStore->products_count + $primaryStore->collections_count + $primaryStore->pages_count) > 0)
            : false;

        return Inertia::render('Onboarding/Index', [
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderByRaw("case `key` when 'free' then 1 when 'growth' then 2 when 'pro' then 3 else 99 end")
                ->orderBy('id')
                ->get(),
            'currentPlanKey' => $account?->plan_key ?? 'free',
            'currentSubscription' => $account?->subscriptions()
                ->with(['plan:id,key,name,monthly_price,shopify_billing_plan_handle', 'store:id,name,shop_domain'])
                ->latest('id')
                ->first(),
            'primaryStore' => $primaryStore,
            'credits' => $credits->summary($account),
            'checklist' => [
                'store_connected' => (bool) $primaryStore,
                'catalog_synced' => $hasSyncedCatalog,
                'free_plan_active' => ($account?->plan_key ?? 'free') === 'free',
                'has_paid_subscription' => $account?->subscriptions()->whereIn('status', ['active', 'trialing', 'pending'])->exists() ?? false,
            ],
        ]);
    }
}
