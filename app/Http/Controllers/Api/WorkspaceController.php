<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeStoreJob;
use App\Jobs\GenerateBlogJob;
use App\Jobs\GenerateBlogTopicsJob;
use App\Jobs\PublishBlogJob;
use App\Jobs\SyncShopifyStoreJob;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\Product;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Services\CreditService;
use App\Services\PlanLimitService;
use App\Support\CatalogAccess;
use App\Support\PlanFeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WorkspaceController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('currentAccount'));
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAccountPermission('analytics.view'), 403);
        $accountId = $request->user()->current_account_id;

        return response()->json([
            'connected_stores' => ShopifyStore::forAccount($accountId)->count(),
            'synced_products' => Product::forAccount($accountId)->count(),
            'generated_topics' => BlogTopic::forAccount($accountId)->count(),
            'generated_blogs' => Blog::forAccount($accountId)->count(),
            'draft_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_DRAFT)->count(),
            'approved_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_APPROVED)->count(),
            'scheduled_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_SCHEDULED)->count(),
            'published_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_PUBLISHED)->count(),
            'failed_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_FAILED)->count(),
        ]);
    }

    public function stores(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAccountPermission('stores.view'), 403);

        return response()->json(ShopifyStore::forAccount($request->user()->current_account_id)->latest()->get());
    }

    public function syncStore(Request $request, ShopifyStore $store): JsonResponse
    {
        abort_unless($request->user()->can('sync', $store), 403);
        SyncShopifyStoreJob::dispatch($store->id);

        return response()->json(['status' => 'queued']);
    }

    public function analyzeStore(Request $request, ShopifyStore $store, PlanLimitService $limits): JsonResponse
    {
        abort_unless($request->user()->can('create', StoreAnalysis::class), 403);
        abort_unless($request->user()->can('view', $store), 403);
        $this->requireModuleAndCatalog($request, 'store_audit');

        try {
            $limits->ensureWithinLimit($request->user()->currentAccount, 'seo_reports');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        AnalyzeStoreJob::dispatch($store->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    }

    public function generateTopics(Request $request, ShopifyStore $store, PlanLimitService $limits, CreditService $credits): JsonResponse
    {
        abort_unless($request->user()->can('create', BlogTopic::class), 403);
        abort_unless($request->user()->can('view', $store), 403);
        $this->requireModuleAndCatalog($request, 'topics');

        $options = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:25'],
            'target_region' => ['nullable', 'string', 'max:64'],
            'target_language' => ['nullable', 'string', 'max:16'],
            'tone' => ['nullable', 'string', 'max:255'],
            'seo_focus' => ['nullable', 'string', 'max:255'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'intent' => ['nullable', 'in:informational,commercial'],
        ]);

        try {
            $limits->ensureWithinLimit($request->user()->currentAccount, 'topics', (int) $options['count']);
            $credits->ensure($request->user()->currentAccount, $credits->topicGenerationCost((int) $options['count']), 'topic generation');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        GenerateBlogTopicsJob::dispatch($store->id, $options, $request->user()->id);

        return response()->json(['status' => 'queued']);
    }

    public function blogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('viewAny', Blog::class), 403);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['blogs'], 403);

        return response()->json(Blog::query()
            ->with('store:id,name')
            ->forAccount($request->user()->current_account_id)
            ->latest()
            ->paginate(25));
    }

    public function generateBlog(Request $request, BlogTopic $topic): JsonResponse
    {
        abort_unless($request->user()->can('approve', $topic), 403);
        $this->requireModuleAndCatalog($request, 'blogs');
        GenerateBlogJob::dispatch($topic->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    }

    public function publishBlog(Request $request, Blog $blog): JsonResponse
    {
        abort_unless($request->user()->can('publish', $blog), 403);
        $this->requireModuleAndCatalog($request, 'blogs');
        PublishBlogJob::dispatch($blog->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    }

    private function requireModuleAndCatalog(Request $request, string $module): void
    {
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)[$module] ?? false, 403);
        abort_unless(CatalogAccess::hasSyncedCatalog($request->user()->current_account_id), 409, 'Sync the Shopify catalog first.');
    }
}
