<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\ShopifyCollection;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CollectionContentService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly CreditService $credits,
    ) {}

    public function generate(ShopifyCollection $collection, array $input, ?User $user = null): ShopifyCollection
    {
        $collection->loadMissing(['store.knowledgeBase']);
        $style = $input['description_style'] ?? 'balanced';
        $creditCost = $this->credits->collectionContentCost($style);

        $this->credits->ensure($collection->account_id, $creditCost, 'collection content generation');

        $prompt = $this->prompt($collection, $input, $style);

        $collection->update([
            'generation_status' => 'running',
            'generation_error' => null,
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $collection->account_id,
            'shopify_store_id' => $collection->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $collection->getMorphClass(),
            'generatable_id' => $collection->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'collection_content_generation',
            'status' => 'running',
            'prompt' => $prompt,
            'metadata' => [
                'description_style' => $style,
                'credit_cost' => $creditCost,
            ],
            'started_at' => now(),
        ]);

        try {
            $result = $this->ai->generate($prompt, [
                'type' => 'collection_content_generation',
                'title' => $collection->title,
                'description_style' => $style,
                'max_tokens' => $style === 'long' ? 2600 : 1800,
            ]);
            $payload = $this->ai->decodeJson($result['content'], []);

            $description = trim((string) ($payload['description_html'] ?? $payload['description'] ?? ''));

            if ($description === '') {
                throw new RuntimeException('AI did not return a collection description.');
            }

            $collection->update([
                'generated_description' => $description,
                'generated_intro' => $payload['intro'] ?? null,
                'generated_benefits' => $payload['benefits'] ?? [],
                'generated_faq' => $payload['faq'] ?? [],
                'generated_meta_title' => Str::limit((string) ($payload['meta_title'] ?? $collection->title), 255, ''),
                'generated_meta_description' => $payload['meta_description'] ?? null,
                'generated_handle' => Str::slug($payload['suggested_handle'] ?? $collection->handle ?? $collection->title),
                'generated_aeo_content' => $payload['aeo_content'] ?? null,
                'generation_status' => 'completed',
                'generation_error' => null,
                'generated_at' => now(),
                'last_optimized_at' => now(),
            ]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->credits->charge($collection->account_id, 'collection_content_generation', $creditCost, $collection, $user, [
                'shopify_store_id' => $collection->shopify_store_id,
                'description_style' => $style,
                'generated_words' => str_word_count(strip_tags($description)),
            ]);
        } catch (Throwable $exception) {
            $collection->update([
                'generation_status' => 'failed',
                'generation_error' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        return $collection->refresh();
    }

    private function prompt(ShopifyCollection $collection, array $input, string $style): string
    {
        $store = $collection->store;
        $knowledgeBase = $store->knowledgeBase;

        return 'Generate Shopify collection page content for SEO and AEO. Return valid JSON only with keys: description_html, intro, benefits, faq, meta_title, meta_description, suggested_handle, aeo_content. Do not invent product materials, certifications, shipping promises, warranties, or discounts unless they are present in the provided collection/store data. '.
            json_encode([
                'description_style' => $style,
                'style_rules' => $this->styleRules($style),
                'customer_brief' => $input['collection_brief'] ?? null,
                'collection' => [
                    'title' => $collection->title,
                    'handle' => $collection->handle,
                    'current_description_text' => Str::limit(strip_tags((string) $collection->description), 2200),
                    'seo_title' => $collection->seo_title,
                    'seo_description' => $collection->seo_description,
                    'product_count' => $collection->product_count,
                ],
                'store' => $store->only(['name', 'country', 'currency', 'default_language', 'primary_locale', 'timezone', 'brand_tone']),
                'knowledge_base' => $knowledgeBase ? [
                    'summary' => $knowledgeBase->summary,
                    'editable_notes' => $knowledgeBase->editable_notes,
                    'brand_profile' => $knowledgeBase->brand_profile,
                    'audience_profile' => $knowledgeBase->audience_profile,
                    'collection_insights' => $knowledgeBase->collection_insights,
                    'seo_opportunities' => $knowledgeBase->seo_opportunities,
                ] : null,
                'related_products' => $store->products()
                    ->where('collections', 'like', '%'.$collection->shopify_collection_id.'%')
                    ->limit(12)
                    ->get(['title', 'product_type', 'vendor', 'seo_title', 'seo_description'])
                    ->toArray(),
            ]);
    }

    private function styleRules(string $style): string
    {
        return match ($style) {
            'short' => 'Write 90-130 words plus 3 short FAQ questions.',
            'long' => 'Write 280-450 words with intro, benefits, product/category explanation, internal-link suggestions, and 5 concise FAQs.',
            default => 'Write 170-260 words with a useful intro, benefit-led detail, and 4 concise FAQs.',
        };
    }
}
