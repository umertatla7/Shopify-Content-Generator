<?php

namespace App\Http\Controllers;

use App\Models\AIGeneration;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\ExistingShopifyBlog;
use App\Models\Product;
use App\Models\PublishingLog;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CreditService $credits): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $account = $request->user()->currentAccount;
        $accountId = $account?->id;

        return Inertia::render('Dashboard', [
            'stats' => [
                'stores' => $accountId ? ShopifyStore::forAccount($accountId)->count() : 0,
                'products' => $accountId ? Product::forAccount($accountId)->count() : 0,
                'existing_blogs' => $accountId ? ExistingShopifyBlog::forAccount($accountId)->count() : 0,
                'topics' => $accountId ? BlogTopic::forAccount($accountId)->count() : 0,
                'blogs' => $accountId ? Blog::forAccount($accountId)->count() : 0,
                'drafts' => $accountId ? Blog::forAccount($accountId)->where('status', Blog::STATUS_DRAFT)->count() : 0,
                'approved' => $accountId ? Blog::forAccount($accountId)->where('status', Blog::STATUS_APPROVED)->count() : 0,
                'scheduled' => $accountId ? Blog::forAccount($accountId)->where('status', Blog::STATUS_SCHEDULED)->count() : 0,
                'published' => $accountId ? Blog::forAccount($accountId)->where('status', Blog::STATUS_PUBLISHED)->count() : 0,
                'failed' => $accountId ? Blog::forAccount($accountId)->where('status', Blog::STATUS_FAILED)->count() : 0,
                'ai_usage' => $accountId ? AIGeneration::forAccount($accountId)->count() : 0,
                'shopify_publishes' => $accountId ? PublishingLog::forAccount($accountId)->where('status', 'succeeded')->count() : 0,
            ],
            'credits' => $credits->summary($account),
            'stores' => $accountId ? ShopifyStore::query()
                ->forAccount($accountId)
                ->withCount(['products', 'collections', 'blogs', 'analyses'])
                ->latest()
                ->limit(6)
                ->get() : [],
            'latestAnalysis' => $accountId ? StoreAnalysis::query()
                ->with('store:id,name')
                ->forAccount($accountId)
                ->where('status', 'completed')
                ->latest()
                ->first() : null,
            'blogs' => $accountId ? Blog::query()
                ->with('store:id,name')
                ->forAccount($accountId)
                ->latest()
                ->limit(8)
                ->get(['id', 'shopify_store_id', 'title', 'primary_keyword', 'seo_score', 'readability_score', 'status', 'created_at', 'scheduled_at', 'published_at', 'featured_image_idea']) : [],
        ]);
    }
}
