<?php

use App\Jobs\AnalyzeStoreJob;
use App\Jobs\GenerateBlogJob;
use App\Jobs\GenerateBlogTopicsJob;
use App\Jobs\PublishBlogJob;
use App\Jobs\SyncShopifyStoreJob;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\Product;
use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/me', fn (Request $request) => $request->user()->load('currentAccount'));

    Route::get('/analytics/summary', function (Request $request) {
        $accountId = $request->user()->current_account_id;

        return [
            'connected_stores' => ShopifyStore::forAccount($accountId)->count(),
            'synced_products' => Product::forAccount($accountId)->count(),
            'generated_topics' => BlogTopic::forAccount($accountId)->count(),
            'generated_blogs' => Blog::forAccount($accountId)->count(),
            'draft_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_DRAFT)->count(),
            'approved_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_APPROVED)->count(),
            'scheduled_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_SCHEDULED)->count(),
            'published_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_PUBLISHED)->count(),
            'failed_blogs' => Blog::forAccount($accountId)->where('status', Blog::STATUS_FAILED)->count(),
        ];
    });

    Route::get('/stores', fn (Request $request) => ShopifyStore::forAccount($request->user()->current_account_id)->latest()->get());
    Route::post('/stores/{store}/sync', function (Request $request, ShopifyStore $store) {
        abort_unless($request->user()->can('sync', $store), 403);
        SyncShopifyStoreJob::dispatch($store->id);

        return response()->json(['status' => 'queued']);
    });

    Route::post('/stores/{store}/analysis', function (Request $request, ShopifyStore $store) {
        abort_unless($request->user()->can('view', $store), 403);
        AnalyzeStoreJob::dispatch($store->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    });

    Route::post('/stores/{store}/topics', function (Request $request, ShopifyStore $store) {
        abort_unless($request->user()->can('view', $store), 403);
        $options = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:25'],
            'target_region' => ['nullable', 'string', 'max:64'],
            'target_language' => ['nullable', 'string', 'max:16'],
            'tone' => ['nullable', 'string', 'max:255'],
            'seo_focus' => ['nullable', 'string', 'max:255'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'intent' => ['nullable', 'in:informational,commercial'],
        ]);
        GenerateBlogTopicsJob::dispatch($store->id, $options, $request->user()->id);

        return response()->json(['status' => 'queued']);
    });

    Route::get('/blogs', fn (Request $request) => Blog::query()
        ->with('store:id,name')
        ->forAccount($request->user()->current_account_id)
        ->latest()
        ->paginate(25));

    Route::post('/topics/{topic}/generate-blog', function (Request $request, BlogTopic $topic) {
        abort_unless($request->user()->can('approve', $topic), 403);
        GenerateBlogJob::dispatch($topic->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    });

    Route::post('/blogs/{blog}/publish', function (Request $request, Blog $blog) {
        abort_unless($request->user()->can('publish', $blog), 403);
        PublishBlogJob::dispatch($blog->id, $request->user()->id);

        return response()->json(['status' => 'queued']);
    });
});
