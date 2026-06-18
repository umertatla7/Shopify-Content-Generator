<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ShopifyCollection;
use App\Services\CollectionContentService;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class CollectionContentController extends Controller
{
    public function generate(Request $request, ShopifyCollection $collection, CollectionContentService $content): JsonResponse
    {
        $this->authorizeCollection($request, $collection);

        $validated = $request->validate([
            'collection_brief' => ['nullable', 'string', 'max:5000'],
            'description_style' => ['required', 'in:short,balanced,long'],
        ]);

        try {
            $previous = $collection->only(['generated_description', 'generated_meta_title', 'generated_meta_description']);
            $collection = $content->generate($collection, $validated, $request->user());
            $this->activity($request, $collection, 'collection.description.generated', 'success', "Generated collection description for {$collection->title}.", $previous, $collection->only(['generated_description', 'generated_meta_title', 'generated_meta_description']));
        } catch (Throwable $exception) {
            $this->activity($request, $collection, 'collection.description.generated', 'failed', "Collection description generation failed for {$collection->title}: {$exception->getMessage()}");

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Collection description generated.',
            'collection' => $this->payload($collection),
        ]);
    }

    public function push(Request $request, ShopifyCollection $collection, ShopifyService $shopify): JsonResponse
    {
        $this->authorizeCollection($request, $collection);

        $validated = $request->validate([
            'generated_description' => ['required', 'string', 'max:30000'],
            'generated_meta_title' => ['nullable', 'string', 'max:255'],
            'generated_meta_description' => ['nullable', 'string', 'max:500'],
            'generated_handle' => ['nullable', 'string', 'max:255'],
            'generated_intro' => ['nullable', 'string', 'max:2000'],
            'generated_benefits' => ['nullable', 'array'],
            'generated_faq' => ['nullable', 'array'],
            'generated_aeo_content' => ['nullable', 'string', 'max:10000'],
        ]);

        $previous = $collection->only(['description', 'handle', 'seo_title', 'seo_description']);

        $collection->update([
            ...$validated,
            'shopify_push_error' => null,
        ]);

        try {
            $updated = $shopify->updateCollectionContent($collection, [
                'description_html' => $validated['generated_description'],
                'seo_title' => $validated['generated_meta_title'] ?? null,
                'seo_description' => $validated['generated_meta_description'] ?? null,
                'handle' => $validated['generated_handle'] ?? null,
            ]);

            $handle = $updated['handle'] ?? $validated['generated_handle'] ?? $collection->handle;

            $collection->update([
                'title' => $updated['title'] ?? $collection->title,
                'handle' => $handle,
                'url' => $handle ? rtrim($collection->store->shop_url, '/').'/collections/'.$handle : $collection->url,
                'description' => $updated['descriptionHtml'] ?? $validated['generated_description'],
                'image_url' => Arr::get($updated, 'image.url', $collection->image_url),
                'seo_title' => Arr::get($updated, 'seo.title', $validated['generated_meta_title'] ?? $collection->seo_title),
                'seo_description' => Arr::get($updated, 'seo.description', $validated['generated_meta_description'] ?? $collection->seo_description),
                'last_synced_at' => now(),
                'last_optimized_at' => now(),
                'shopify_pushed_at' => now(),
                'shopify_push_error' => null,
                'payload' => $updated ?: $collection->payload,
            ]);

            $this->activity($request, $collection, 'collection.description.pushed', 'success', "Pushed collection description to Shopify for {$collection->title}.", $previous, $collection->only(['description', 'handle', 'seo_title', 'seo_description']));
        } catch (Throwable $exception) {
            $collection->update(['shopify_push_error' => $exception->getMessage()]);
            $this->activity($request, $collection, 'collection.description.pushed', 'failed', "Collection push failed for {$collection->title}: {$exception->getMessage()}", $previous, null);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Collection pushed to Shopify.',
            'collection' => $this->payload($collection->refresh()),
        ]);
    }

    private function authorizeCollection(Request $request, ShopifyCollection $collection): void
    {
        abort_unless($collection->account_id === $request->user()->current_account_id, 403);
        abort_unless($request->user()->hasAccountPermission('stores.manage'), 403);
    }

    private function payload(ShopifyCollection $collection): array
    {
        return $collection->loadMissing('store:id,name,shop_domain,shop_url')->toArray();
    }

    private function activity(Request $request, ShopifyCollection $collection, string $action, string $status, string $description, ?array $previous = null, ?array $new = null): void
    {
        ActivityLog::query()->create([
            'account_id' => $collection->account_id,
            'shopify_store_id' => $collection->shopify_store_id,
            'user_id' => $request->user()?->id,
            'subject_type' => $collection->getMorphClass(),
            'subject_id' => $collection->id,
            'action' => $action,
            'entity_type' => 'collection',
            'status' => $status,
            'description' => $description,
            'previous_values' => $previous,
            'new_values' => $new,
            'ip_address' => $request->ip(),
        ]);
    }
}
