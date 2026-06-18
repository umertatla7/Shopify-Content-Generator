<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\ShopifyStore;
use App\Models\StoreKnowledgeBase;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Str;
use Throwable;

class StoreKnowledgeBaseService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly UsageTrackingService $usage,
    ) {}

    public function generate(ShopifyStore $store, ?User $user = null): StoreKnowledgeBase
    {
        $store->loadMissing(['products', 'collections', 'pages', 'credential']);
        $snapshot = $this->snapshot($store);
        $prompt = $this->prompt($store, $snapshot);

        $knowledgeBase = StoreKnowledgeBase::query()->updateOrCreate(
            ['shopify_store_id' => $store->id],
            [
                'account_id' => $store->account_id,
                'status' => 'running',
                'source_snapshot' => $snapshot,
                'error_message' => null,
            ]
        );

        $generation = AIGeneration::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'user_id' => $user?->id,
            'generatable_type' => $knowledgeBase->getMorphClass(),
            'generatable_id' => $knowledgeBase->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'store_knowledge_base',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
        ]);

        try {
            $result = $this->ai->generate($prompt, ['type' => 'store_knowledge_base']);
            $payload = $this->ai->decodeJson($result['content'], []);

            $knowledgeBase->update([
                'status' => 'completed',
                'summary' => $payload['summary'] ?? null,
                'brand_profile' => $payload['brand_profile'] ?? [],
                'audience_profile' => $payload['audience_profile'] ?? [],
                'product_insights' => $payload['product_insights'] ?? [],
                'collection_insights' => $payload['collection_insights'] ?? [],
                'content_insights' => $payload['content_insights'] ?? [],
                'seo_opportunities' => $payload['seo_opportunities'] ?? [],
                'generated_at' => now(),
                'error_message' => null,
            ]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->usage->record($store->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $knowledgeBase, $user, [
                'shopify_store_id' => $store->id,
                'type' => 'store_knowledge_base',
            ]);
        } catch (Throwable $exception) {
            $knowledgeBase->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $knowledgeBase->refresh();
    }

    public function snapshot(ShopifyStore $store): array
    {
        return [
            'store' => [
                ...$store->only(['name', 'shop_domain', 'shop_url', 'country', 'default_language', 'brand_tone']),
                'metadata' => $store->metadata,
            ],
            'products' => $store->products()->latest('last_synced_at')->limit(75)->get()
                ->map(fn ($product) => [
                    'title' => $product->title,
                    'handle' => $product->handle,
                    'url' => $product->url,
                    'type' => $product->product_type,
                    'vendor' => $product->vendor,
                    'status' => $product->status,
                    'tags' => $product->tags,
                    'description' => Str::limit(strip_tags((string) $product->description), 700),
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                ])->all(),
            'collections' => $store->collections()->latest('last_synced_at')->limit(50)->get()
                ->map(fn ($collection) => [
                    'title' => $collection->title,
                    'handle' => $collection->handle,
                    'url' => $collection->url,
                    'description' => Str::limit(strip_tags((string) $collection->description), 700),
                ])->all(),
            'pages' => $store->pages()->latest('last_synced_at')->limit(30)->get()
                ->map(fn ($page) => [
                    'title' => $page->title,
                    'handle' => $page->handle,
                    'url' => $page->url,
                    'summary' => $page->summary,
                    'body' => Str::limit(strip_tags((string) $page->body), 1000),
                    'is_published' => $page->is_published,
                ])->all(),
            'existing_blogs' => $store->existingBlogs()->latest('last_synced_at')->limit(30)->get()
                ->map(fn ($blog) => [
                    'title' => $blog->title,
                    'handle' => $blog->handle,
                    'url' => $blog->url,
                    'excerpt' => Str::limit(strip_tags((string) $blog->excerpt), 500),
                    'body' => Str::limit(strip_tags((string) $blog->body), 900),
                    'tags' => $blog->tags,
                ])->all(),
        ];
    }

    private function prompt(ShopifyStore $store, array $snapshot): string
    {
        return 'Build an editable knowledge base for this Shopify store so future SEO blog topics and articles stay accurate to the store. Return JSON with keys: summary, brand_profile, audience_profile, product_insights, collection_insights, content_insights, seo_opportunities. Keep it factual and grounded only in the provided store data. '.
            json_encode($snapshot);
    }
}
