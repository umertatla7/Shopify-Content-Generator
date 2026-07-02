<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\PlanLimitService;
use App\Services\ProductContentService;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class ProductContentController extends Controller
{
    public function generate(Request $request, Product $product, ProductContentService $content, PlanLimitService $planLimits): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'base_title' => ['required', 'string', 'max:255'],
            'base_description' => ['required', 'string', 'max:5000'],
            'description_style' => ['required', 'in:short,balanced,bullets,long'],
        ]);

        try {
            $planLimits->ensureWithinLimit($request->user()->currentAccount, 'product_descriptions');
            $product = $content->generate($product, $validated, $request->user());
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Product content generated.',
            'product' => $this->payload($product),
        ]);
    }

    public function push(Request $request, Product $product, ShopifyService $shopify): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'generated_title' => ['required', 'string', 'max:255'],
            'generated_description' => ['required', 'string', 'max:20000'],
            'generated_seo_title' => ['nullable', 'string', 'max:255'],
            'generated_seo_description' => ['nullable', 'string', 'max:500'],
            'publish' => ['sometimes', 'boolean'],
        ]);

        $product->update([
            'generated_title' => $validated['generated_title'],
            'generated_description' => $validated['generated_description'],
            'generated_seo_title' => $validated['generated_seo_title'] ?? null,
            'generated_seo_description' => $validated['generated_seo_description'] ?? null,
            'shopify_content_push_error' => null,
        ]);

        try {
            $updated = $shopify->updateProductContent($product, [
                'title' => $validated['generated_title'],
                'description_html' => $validated['generated_description'],
                'seo_title' => $validated['generated_seo_title'] ?? null,
                'seo_description' => $validated['generated_seo_description'] ?? null,
                'publish' => (bool) ($validated['publish'] ?? false),
            ]);

            $product->update([
                'title' => $updated['title'] ?? $validated['generated_title'],
                'handle' => $updated['handle'] ?? $product->handle,
                'url' => $updated['onlineStoreUrl'] ?? $product->url,
                'description' => $updated['descriptionHtml'] ?? $validated['generated_description'],
                'product_type' => $updated['productType'] ?? $product->product_type,
                'vendor' => $updated['vendor'] ?? $product->vendor,
                'status' => strtolower($updated['status'] ?? $product->status ?? ''),
                'tags' => $updated['tags'] ?? $product->tags,
                'collections' => Arr::get($updated, 'collections.nodes', $product->collections),
                'image_url' => Arr::get($updated, 'featuredImage.url', $product->image_url),
                'seo_title' => Arr::get($updated, 'seo.title', $validated['generated_seo_title'] ?? $product->seo_title),
                'seo_description' => Arr::get($updated, 'seo.description', $validated['generated_seo_description'] ?? $product->seo_description),
                'published_at' => $updated['publishedAt'] ?? $product->published_at,
                'last_synced_at' => now(),
                'shopify_content_pushed_at' => now(),
                'shopify_content_push_error' => null,
                'payload' => $updated ?: $product->payload,
            ]);
        } catch (Throwable $exception) {
            $product->update(['shopify_content_push_error' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => ($validated['publish'] ?? false)
                ? 'Product pushed and published on Shopify.'
                : 'Product content pushed to Shopify.',
            'product' => $this->payload($product->refresh()),
        ]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->account_id === $request->user()->current_account_id, 403);
        abort_unless($request->user()->hasAccountPermission('stores.manage'), 403);
    }

    private function payload(Product $product): array
    {
        return $product->loadMissing('store:id,name,shop_domain,shop_url')->toArray();
    }
}
