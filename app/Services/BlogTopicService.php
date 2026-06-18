<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\BlogTopic;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Collection;
use Throwable;

class BlogTopicService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly UsageTrackingService $usage,
        private readonly CreditService $credits,
    ) {}

    /**
     * @return Collection<int, BlogTopic>
     */
    public function generate(ShopifyStore $store, array $options, ?User $user = null)
    {
        $count = max(1, min(25, (int) ($options['count'] ?? 5)));
        $analysis = StoreAnalysis::query()
            ->where('shopify_store_id', $store->id)
            ->where('status', 'completed')
            ->latest()
            ->first();
        $knowledgeBase = $store->knowledgeBase()->first();
        $prompt = $this->prompt($store, $analysis, $options, $count);

        $generation = AIGeneration::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'user_id' => $user?->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'topic_generation',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
            'metadata' => $options,
        ]);

        try {
            $result = $this->ai->generate($prompt, [
                'type' => 'topic_generation',
                'count' => $count,
                ...$options,
            ]);
            $payload = $this->ai->decodeJson($result['content'], ['topics' => []]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $topics = collect($payload['topics'] ?? [])->take($count)->map(function (array $topic) use ($store, $analysis, $generation, $options): BlogTopic {
                $tones = $options['tone'] ?? [];
                $tone = is_array($tones) ? implode(', ', $tones) : $tones;

                return BlogTopic::query()->create([
                    'account_id' => $store->account_id,
                    'shopify_store_id' => $store->id,
                    'store_analysis_id' => $analysis?->id,
                    'ai_generation_id' => $generation->id,
                    'title' => $topic['title'] ?? 'Untitled SEO blog topic',
                    'primary_keyword' => $topic['primary_keyword'] ?? null,
                    'secondary_keywords' => $topic['secondary_keywords'] ?? [],
                    'search_intent' => $topic['search_intent'] ?? ($options['intent_label'] ?? $options['intent'] ?? null),
                    'suggested_outline' => $topic['suggested_outline'] ?? [],
                    'related_products' => $topic['related_products'] ?? [],
                    'related_collections' => $topic['related_collections'] ?? ($options['collections'] ?? []),
                    'opportunity_score' => $topic['opportunity_score'] ?? null,
                    'estimated_article_size' => $topic['estimated_article_size'] ?? $topic['estimated_word_count'] ?? '1,200-1,600 words',
                    'target_region' => $options['target_region'] ?? $store->country,
                    'target_language' => $options['target_language'] ?? $store->default_language,
                    'tone' => $tone ?: $store->brand_tone,
                    'seo_focus' => $options['seo_focus'] ?? null,
                    'product_category' => $options['product_category'] ?? implode(', ', $options['collection_titles'] ?? []),
                    'status' => 'generated',
                    'prompt' => $generation->prompt,
                    'response' => $topic,
                ]);
            });

            $this->usage->record($store->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $generation, $user, [
                'shopify_store_id' => $store->id,
                'type' => 'topic_generation',
                'count' => $topics->count(),
            ]);

            if ($topics->isNotEmpty()) {
                $this->credits->charge($store->account_id, 'topic_generation', $this->credits->topicGenerationCost($topics->count()), $generation, $user, [
                    'shopify_store_id' => $store->id,
                    'topic_count' => $topics->count(),
                ]);
            }

            return $topics;
        } catch (Throwable $exception) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function approve(BlogTopic $topic, User $user): BlogTopic
    {
        $topic->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $topic->refresh();
    }

    private function prompt(ShopifyStore $store, ?StoreAnalysis $analysis, array $options, int $count): string
    {
        $knowledgeBase = $store->knowledgeBase()->first();

        return 'Generate SEO and AEO blog topic ideas for this Shopify store based on the editable store knowledge base and selected filters. Return JSON {"topics":[{"title":"","primary_keyword":"","secondary_keywords":[],"search_intent":"","suggested_category":"","estimated_article_size":"1200-1500 words","suggested_outline":[],"related_products":[],"related_collections":[],"opportunity_score":80}]}. '.
            json_encode([
                'count' => $count,
                'store' => $store->only(['name', 'shop_domain', 'country', 'currency', 'default_language', 'primary_locale', 'timezone', 'brand_tone']),
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
                'analysis' => $analysis?->response,
                'options' => $options,
                'products' => $store->products()->limit(25)->get(['id', 'title', 'handle', 'product_type'])->toArray(),
                'collections' => $store->collections()->limit(15)->get(['id', 'title', 'handle'])->toArray(),
            ]);
    }
}
