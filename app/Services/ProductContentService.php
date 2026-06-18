<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\Product;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductContentService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly CreditService $credits,
    ) {}

    public function generate(Product $product, array $input, ?User $user = null): Product
    {
        $product->loadMissing(['store.knowledgeBase']);
        $style = $input['description_style'] ?? 'balanced';
        $creditCost = $this->credits->productContentCost($style);

        $this->credits->ensure($product->account_id, $creditCost, 'product content generation');

        $prompt = $this->prompt($product, $input, $style);

        $product->update([
            'content_generation_status' => 'running',
            'content_generation_error' => null,
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $product->account_id,
            'shopify_store_id' => $product->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $product->getMorphClass(),
            'generatable_id' => $product->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'product_content_generation',
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
                'type' => 'product_content_generation',
                'title' => $input['base_title'] ?: $product->title,
                'description_style' => $style,
                'max_tokens' => $style === 'long' ? 2200 : 1400,
            ]);
            $payload = $this->ai->decodeJson($result['content'], []);

            $generatedTitle = trim((string) ($payload['title'] ?? ''));
            $generatedDescription = trim((string) ($payload['description_html'] ?? $payload['description'] ?? ''));

            if ($generatedTitle === '' || $generatedDescription === '') {
                throw new RuntimeException('AI did not return both a title and description.');
            }

            $product->update([
                'generated_title' => Str::limit($generatedTitle, 255, ''),
                'generated_description' => $generatedDescription,
                'generated_seo_title' => Str::limit((string) ($payload['seo_title'] ?? $generatedTitle), 255, ''),
                'generated_seo_description' => $payload['seo_description'] ?? null,
                'generated_description_style' => $style,
                'content_generation_status' => 'completed',
                'content_generation_error' => null,
                'content_generated_at' => now(),
            ]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->credits->charge($product->account_id, 'product_content_generation', $creditCost, $product, $user, [
                'shopify_store_id' => $product->shopify_store_id,
                'description_style' => $style,
                'generated_words' => str_word_count(strip_tags($generatedDescription)),
            ]);
        } catch (Throwable $exception) {
            $product->update([
                'content_generation_status' => 'failed',
                'content_generation_error' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        return $product->refresh();
    }

    private function prompt(Product $product, array $input, string $style): string
    {
        $store = $product->store;
        $knowledgeBase = $store->knowledgeBase;

        return 'Generate an SEO-friendly Shopify product title and product description. Return valid JSON only with keys: title, description_html, seo_title, seo_description. Do not invent materials, gemstones, sizes, warranties, or shipping promises unless the provided product/customer details mention them. Use the customer details as the source of truth and improve clarity, SEO, and buying confidence. '.
            json_encode([
                'description_style' => $style,
                'style_rules' => $this->styleRules($style),
                'customer_input' => [
                    'base_title' => $input['base_title'] ?? null,
                    'base_description' => $input['base_description'] ?? null,
                ],
                'existing_product' => [
                    'title' => $product->title,
                    'description_text' => Str::limit(strip_tags((string) $product->description), 2000),
                    'product_type' => $product->product_type,
                    'vendor' => $product->vendor,
                    'tags' => $product->tags ?? [],
                    'collections' => collect($product->collections ?? [])->pluck('title')->filter()->values()->all(),
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                ],
                'store' => $store->only(['name', 'country', 'default_language', 'brand_tone']),
                'knowledge_base' => $knowledgeBase ? [
                    'summary' => $knowledgeBase->summary,
                    'editable_notes' => $knowledgeBase->editable_notes,
                    'brand_profile' => $knowledgeBase->brand_profile,
                    'audience_profile' => $knowledgeBase->audience_profile,
                    'product_insights' => $knowledgeBase->product_insights,
                    'seo_opportunities' => $knowledgeBase->seo_opportunities,
                ] : null,
                'image_hint' => $product->image_url ? 'Product image is available in Shopify sync.' : null,
                'payload_context' => Arr::only($product->payload ?? [], ['handle', 'status', 'onlineStoreUrl']),
            ]);
    }

    private function styleRules(string $style): string
    {
        return match ($style) {
            'short' => 'Write 60-100 words in one or two concise HTML paragraphs.',
            'bullets' => 'Write one short intro paragraph plus 4-6 benefit-led bullet points in HTML.',
            'long' => 'Write 220-350 words with useful HTML paragraphs and natural buyer-focused detail.',
            default => 'Write 120-180 words in clear HTML paragraphs with natural SEO keywords.',
        };
    }
}
