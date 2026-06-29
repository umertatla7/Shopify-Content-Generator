<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\Blog;
use App\Models\BlogRevision;
use App\Models\BlogTopic;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Str;
use Throwable;

class BlogGenerationService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly SEOScoringService $seo,
        private readonly UsageTrackingService $usage,
        private readonly CreditService $credits,
        private readonly PlanLimitService $planLimits,
    ) {}

    public function generateFromTopic(BlogTopic $topic, ?User $user = null): Blog
    {
        $topic->loadMissing('store');
        $this->planLimits->ensureWithinLimit($topic->account_id, 'blogs');
        $prompt = $this->prompt($topic);

        $blog = Blog::query()->create([
            'account_id' => $topic->account_id,
            'shopify_store_id' => $topic->shopify_store_id,
            'blog_topic_id' => $topic->id,
            'generated_by' => $user?->id,
            'title' => $topic->title,
            'primary_keyword' => $topic->primary_keyword,
            'secondary_keywords' => $topic->secondary_keywords,
            'status' => Blog::STATUS_DRAFT,
            'generation_status' => 'running',
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $blog->account_id,
            'shopify_store_id' => $blog->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $blog->getMorphClass(),
            'generatable_id' => $blog->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'blog_generation',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
        ]);

        try {
            $result = $this->ai->generate($prompt, [
                'type' => 'blog_generation',
                'title' => $topic->title,
                'primary_keyword' => $topic->primary_keyword,
            ]);
            $payload = $this->ai->decodeJson($result['content']);
            $scores = $this->seo->score($payload);

            $blog->update([
                'title' => $payload['title'] ?? $topic->title,
                'seo_title' => $payload['seo_title'] ?? null,
                'meta_title' => $payload['meta_title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'slug' => $payload['slug'] ?? Str::slug($payload['title'] ?? $topic->title),
                'excerpt' => $payload['excerpt'] ?? null,
                'body' => null,
                'faq' => $payload['faq'] ?? [],
                'internal_links' => $payload['internal_links'] ?? [],
                'product_links' => $payload['product_links'] ?? [],
                'featured_image_idea' => $payload['featured_image_idea'] ?? null,
                'primary_keyword' => $payload['primary_keyword'] ?? $topic->primary_keyword,
                'secondary_keywords' => $payload['secondary_keywords'] ?? $topic->secondary_keywords,
                'seo_score' => $payload['seo_score'] ?? $scores['seo_score'],
                'readability_score' => $payload['readability_score'] ?? $scores['readability_score'],
                'status' => Blog::STATUS_DRAFT,
                'generation_status' => 'completed',
                'payload' => $payload,
            ]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->snapshot($blog, $user, 'Initial AI draft metadata');
            $this->usage->record($blog->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $blog, $user, [
                'shopify_store_id' => $blog->shopify_store_id,
                'type' => 'blog_generation',
            ]);
        } catch (Throwable $exception) {
            $blog->update([
                'status' => Blog::STATUS_FAILED,
                'generation_status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $blog->refresh();
    }

    public function rewrite(Blog $blog, string $instruction, ?User $user = null): Blog
    {
        $prompt = 'Rewrite or improve this Shopify SEO blog according to the instruction. Return JSON with updated title, meta_title, meta_description, slug, excerpt, body, faq, internal_links, product_links. '.
            json_encode([
                'instruction' => $instruction,
                'blog' => $blog->only(['title', 'meta_title', 'meta_description', 'slug', 'excerpt', 'body', 'faq', 'internal_links', 'product_links', 'primary_keyword', 'secondary_keywords']),
            ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $blog->account_id,
            'shopify_store_id' => $blog->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $blog->getMorphClass(),
            'generatable_id' => $blog->id,
            'type' => 'rewrite',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
        ]);

        $result = $this->ai->generate($prompt, ['type' => 'rewrite']);
        $payload = $this->ai->decodeJson($result['content'], []);
        $updates = array_filter([
            'title' => $payload['title'] ?? null,
            'meta_title' => $payload['meta_title'] ?? null,
            'meta_description' => $payload['meta_description'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'excerpt' => $payload['excerpt'] ?? null,
            'body' => $payload['body'] ?? null,
            'faq' => $payload['faq'] ?? null,
            'internal_links' => $payload['internal_links'] ?? null,
            'product_links' => $payload['product_links'] ?? null,
        ], fn ($value) => $value !== null);

        $updates += $this->seo->score([...$blog->toArray(), ...$updates]);
        $updates['status'] = Blog::STATUS_NEEDS_REVIEW;

        $blog->update($updates);
        $generation->update([
            'provider' => $result['provider'],
            'model' => $result['model'],
            'status' => 'completed',
            'response' => $result['content'],
            'token_usage' => $result['usage'],
            'completed_at' => now(),
        ]);

        $this->snapshot($blog, $user, $instruction);

        return $blog->refresh();
    }

    public function generateBody(Blog $blog, ?User $user = null, array $options = []): Blog
    {
        @set_time_limit((int) config('services.openai.long_task_timeout', 180));

        $blog->loadMissing(['store.knowledgeBase', 'topic']);
        $prompt = $this->bodyPrompt($blog, $options);
        $estimatedCredits = $this->credits->wordsToCredits($this->estimatedWords($blog->topic?->estimated_article_size));

        $this->credits->ensure($blog->account_id, $estimatedCredits, 'full blog body generation');

        AIGeneration::query()
            ->where('generatable_type', $blog->getMorphClass())
            ->where('generatable_id', $blog->id)
            ->where('type', 'blog_body_generation')
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error_message' => 'Previous body generation attempt timed out before completion.',
                'completed_at' => now(),
            ]);

        $blog->update([
            'generation_status' => 'running',
            'failure_message' => null,
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $blog->account_id,
            'shopify_store_id' => $blog->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $blog->getMorphClass(),
            'generatable_id' => $blog->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'blog_body_generation',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
            'metadata' => $options,
        ]);

        try {
            $estimatedSize = $blog->topic?->estimated_article_size ?? '1,000-1,500 words';
            $result = $this->ai->generate($prompt, [
                'type' => 'blog_body_generation',
                'title' => $blog->title,
                'primary_keyword' => $blog->primary_keyword,
                'estimated_article_size' => $estimatedSize,
                'tone' => $options['tone'] ?? $blog->topic?->tone ?? $blog->store?->brand_tone,
                'max_tokens' => min((int) config('services.openai.max_tokens', 6000), 4500),
                'timeout' => min((int) config('services.openai.long_task_timeout', 180), 120),
            ]);
            $payload = $this->ai->decodeJson($result['content'], []);
            $updates = array_filter([
                'body' => $payload['body'] ?? null,
                'faq' => $payload['faq'] ?? null,
                'internal_links' => $payload['internal_links'] ?? null,
                'product_links' => $payload['product_links'] ?? null,
                'featured_image_idea' => $payload['featured_image_idea'] ?? null,
                'seo_score' => $payload['seo_score'] ?? null,
                'readability_score' => $payload['readability_score'] ?? null,
            ], fn ($value) => $value !== null);

            if (! isset($updates['body'])) {
                throw new \RuntimeException('AI did not return a complete blog body. Please try again, or reduce the estimated article size for this topic.');
            }

            $updates += $this->seo->score([...$blog->toArray(), ...$updates]);
            $updates['generation_status'] = 'completed';
            $updates['status'] = Blog::STATUS_NEEDS_REVIEW;

            $blog->update($updates);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->snapshot($blog->refresh(), $user, 'Generated full blog body');
            $this->usage->record($blog->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $blog, $user, [
                'shopify_store_id' => $blog->shopify_store_id,
                'type' => 'blog_body_generation',
            ]);

            $wordCount = str_word_count(strip_tags((string) $updates['body']));
            $this->credits->charge($blog->account_id, 'blog_body_generation', $this->credits->wordsToCredits($wordCount), $blog, $user, [
                'shopify_store_id' => $blog->shopify_store_id,
                'generated_words' => $wordCount,
                'estimated_article_size' => $estimatedSize,
            ]);
        } catch (Throwable $exception) {
            $blog->update([
                'generation_status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $blog->refresh();
    }

    public function snapshot(Blog $blog, ?User $user, string $summary): BlogRevision
    {
        $nextVersion = ((int) $blog->revisions()->max('version')) + 1;

        return BlogRevision::query()->create([
            'account_id' => $blog->account_id,
            'blog_id' => $blog->id,
            'user_id' => $user?->id,
            'version' => $nextVersion,
            'title' => $blog->title,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'slug' => $blog->slug,
            'excerpt' => $blog->excerpt,
            'body' => $blog->body,
            'faq' => $blog->faq,
            'internal_links' => $blog->internal_links,
            'product_links' => $blog->product_links,
            'change_summary' => $summary,
        ]);
    }

    private function prompt(BlogTopic $topic): string
    {
        $store = $topic->store;

        return 'Create the SEO draft shell for a Shopify blog article, but do not write the full article body yet. Return JSON with title, seo_title, meta_title, meta_description, slug, excerpt, faq array, internal_links array, product_links array, featured_image_idea, primary_keyword, secondary_keywords, seo_score, readability_score. The full body will be generated in a later step. '.
            json_encode([
                'store' => $store->only(['name', 'country', 'default_language', 'brand_tone']),
                'knowledge_base' => $store->knowledgeBase ? [
                    'summary' => $store->knowledgeBase->summary,
                    'editable_notes' => $store->knowledgeBase->editable_notes,
                    'brand_profile' => $store->knowledgeBase->brand_profile,
                    'audience_profile' => $store->knowledgeBase->audience_profile,
                    'product_insights' => $store->knowledgeBase->product_insights,
                    'collection_insights' => $store->knowledgeBase->collection_insights,
                    'content_insights' => $store->knowledgeBase->content_insights,
                    'seo_opportunities' => $store->knowledgeBase->seo_opportunities,
                ] : null,
                'topic' => $topic->only(['title', 'primary_keyword', 'secondary_keywords', 'search_intent', 'estimated_article_size', 'suggested_outline', 'target_region', 'target_language', 'tone', 'seo_focus']),
                'products' => $this->productContext($store, 12),
                'collections' => $this->collectionContext($store, 8),
            ]);
    }

    private function bodyPrompt(Blog $blog, array $options = []): string
    {
        $store = $blog->store;
        $knowledgeBase = $store->knowledgeBase;
        $estimatedSize = $blog->topic?->estimated_article_size ?? '1,000-1,500 words';
        $confirmedTone = $options['tone'] ?? $blog->topic?->tone ?? $store->brand_tone ?? 'Professional';

        return 'Write the complete long-form body for this Shopify SEO blog. Return valid JSON only with keys: body, faq, internal_links, product_links, featured_image_idea, seo_score, readability_score. The body must be HTML with one H1, multiple H2/H3 sections, useful paragraphs, natural product/collection references, and enough depth to match the estimated article size. If the estimated article size says 1000 words, write at least about 1000 words. Match the confirmed blog tone exactly. Do not return a short placeholder, outline, or summary. '.
            json_encode([
                'estimated_article_size' => $estimatedSize,
                'confirmed_blog_tone' => $confirmedTone,
                'existing_blog_metadata' => $blog->only(['title', 'meta_title', 'meta_description', 'slug', 'excerpt', 'primary_keyword', 'secondary_keywords']),
                'topic' => $blog->topic?->only(['title', 'primary_keyword', 'secondary_keywords', 'search_intent', 'estimated_article_size', 'suggested_outline', 'target_region', 'target_language', 'tone', 'seo_focus', 'product_category', 'related_collections']),
                'store' => $store->only(['name', 'country', 'default_language', 'brand_tone']),
                'knowledge_base' => $knowledgeBase ? [
                    'summary' => $knowledgeBase->summary,
                    'editable_notes' => $knowledgeBase->editable_notes,
                    'brand_profile' => $knowledgeBase->brand_profile,
                    'audience_profile' => $knowledgeBase->audience_profile,
                    'product_insights' => $knowledgeBase->product_insights,
                    'collection_insights' => $knowledgeBase->collection_insights,
                    'content_insights' => $knowledgeBase->content_insights,
                    'seo_opportunities' => $knowledgeBase->seo_opportunities,
                ] : null,
                'products' => $this->productContext($store, 15),
                'collections' => $this->collectionContext($store, 10),
                'store_pages' => $store->pages()->limit(6)->get(['title', 'handle', 'url', 'summary'])->map(fn ($page) => [
                    'title' => $page->title,
                    'handle' => $page->handle,
                    'url' => $page->url,
                    'summary' => Str::limit((string) $page->summary, 250),
                ])->toArray(),
            ]);
    }

    private function estimatedWords(?string $estimatedSize): int
    {
        preg_match_all('/\d[\d,]*/', (string) $estimatedSize, $matches);
        $numbers = collect($matches[0] ?? [])
            ->map(fn (string $number) => (int) str_replace(',', '', $number))
            ->filter()
            ->values();

        return max(300, (int) ($numbers->max() ?: 1200));
    }

    private function productContext($store, int $limit): array
    {
        return $store->products()->limit($limit)->get(['id', 'title', 'handle', 'url', 'description', 'product_type', 'vendor'])->map(fn ($product) => [
            'id' => $product->id,
            'title' => $product->title,
            'handle' => $product->handle,
            'url' => $product->url,
            'product_type' => $product->product_type,
            'vendor' => $product->vendor,
            'description_summary' => Str::limit(strip_tags((string) $product->description), 300),
        ])->toArray();
    }

    private function collectionContext($store, int $limit): array
    {
        return $store->collections()->limit($limit)->get(['id', 'title', 'handle', 'url', 'description'])->map(fn ($collection) => [
            'id' => $collection->id,
            'title' => $collection->title,
            'handle' => $collection->handle,
            'url' => $collection->url,
            'description_summary' => Str::limit(strip_tags((string) $collection->description), 250),
        ])->toArray();
    }
}
