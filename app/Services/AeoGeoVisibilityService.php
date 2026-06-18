<?php

namespace App\Services;

use App\Models\AeoGeoPromptCheck;
use App\Models\AeoGeoVisibilityReport;
use App\Models\Blog;
use App\Models\ShopifyCollection;
use App\Models\ShopifyStore;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AeoGeoVisibilityService
{
    public function generate(ShopifyStore $store, ?User $user = null): AeoGeoVisibilityReport
    {
        $store->loadMissing([
            'products',
            'collections',
            'pages',
            'existingBlogs',
            'blogs',
            'knowledgeBase',
            'latestAnalysis',
        ]);

        $snapshot = $this->sourceSnapshot($store);
        $prompts = $this->promptChecks($store);
        $promptStats = $this->promptStats($prompts);
        $scores = $this->scores($store, $snapshot, $promptStats);
        $gaps = $this->contentGaps($store, $snapshot, $promptStats, $scores);
        $recommendations = $this->recommendations($store, $snapshot, $promptStats, $scores);
        $findings = $this->findings($store, $snapshot, $scores);
        $topQuestions = $prompts->take(12)->map(fn (array $prompt): array => [
            'question' => $prompt['prompt'],
            'intent' => $prompt['intent'],
            'status' => $prompt['status'],
            'score' => $prompt['score'],
        ])->values()->all();

        $report = AeoGeoVisibilityReport::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'generated_by' => $user?->id,
            'status' => 'completed',
            'overall_score' => $scores['overall_score'],
            'aeo_score' => $scores['aeo_score'],
            'geo_score' => $scores['geo_score'],
            'llm_readiness_score' => $scores['llm_readiness_score'],
            'answer_coverage_score' => $scores['answer_coverage_score'],
            'entity_confidence_score' => $scores['entity_confidence_score'],
            'content_depth_score' => $scores['content_depth_score'],
            'schema_readiness_score' => $scores['schema_readiness_score'],
            'prompt_coverage_score' => $scores['prompt_coverage_score'],
            'tracked_prompt_count' => $promptStats['total'],
            'covered_prompt_count' => $promptStats['covered'],
            'partial_prompt_count' => $promptStats['partial'],
            'missing_prompt_count' => $promptStats['missing'],
            'summary' => $this->summary($store, $scores, $promptStats),
            'findings' => $findings,
            'recommendations' => $recommendations,
            'content_gaps' => $gaps,
            'top_questions' => $topQuestions,
            'source_snapshot' => $snapshot,
            'completed_at' => now(),
        ]);

        foreach ($prompts as $prompt) {
            AeoGeoPromptCheck::query()->create([
                'account_id' => $store->account_id,
                'aeo_geo_visibility_report_id' => $report->id,
                'shopify_store_id' => $store->id,
                'prompt' => $prompt['prompt'],
                'intent' => $prompt['intent'],
                'target_entity_type' => $prompt['target_entity_type'] ?? null,
                'target_entity_id' => $prompt['target_entity_id'] ?? null,
                'target_entity_label' => $prompt['target_entity_label'] ?? null,
                'status' => $prompt['status'],
                'score' => $prompt['score'],
                'evidence' => $prompt['evidence'],
                'recommended_source_url' => $prompt['recommended_source_url'] ?? null,
                'recommendation' => $prompt['recommendation'],
                'metadata' => [
                    'rule_version' => 'phase_3_v1',
                ],
            ]);
        }

        return $report->load(['store:id,name,shop_domain', 'promptChecks']);
    }

    private function sourceSnapshot(ShopifyStore $store): array
    {
        $products = $store->products;
        $collections = $store->collections;
        $pages = $store->pages;
        $existingBlogs = $store->existingBlogs;
        $localBlogs = $store->blogs;
        $publishedLocalBlogs = $localBlogs->where('status', Blog::STATUS_PUBLISHED);

        $productsWithDescriptions = $products->filter(fn ($product) => $this->wordCount($product->description) >= 40);
        $productsWithSeo = $products->filter(fn ($product) => filled($product->seo_title) && filled($product->seo_description));
        $collectionsWithDescriptions = $collections->filter(fn ($collection) => $this->wordCount($collection->description ?: $collection->generated_description) >= 40);
        $blogsWithFaqs = $localBlogs->filter(fn ($blog) => count((array) $blog->faq) > 0);
        $blogsWithLinks = $localBlogs->filter(fn ($blog) => count((array) $blog->internal_links) > 0 || count((array) $blog->product_links) > 0);
        $answerPages = $pages->filter(fn ($page) => Str::contains(Str::lower($page->title.' '.$page->body), ['faq', 'shipping', 'returns', 'about', 'contact', 'care']));
        $knowledgeBase = $store->knowledgeBase;

        return [
            'products' => [
                'total' => $products->count(),
                'with_descriptions' => $productsWithDescriptions->count(),
                'with_seo' => $productsWithSeo->count(),
                'missing_descriptions' => $products->diff($productsWithDescriptions)->pluck('title')->take(10)->values()->all(),
                'product_types' => $products->pluck('product_type')->filter()->unique()->take(12)->values()->all(),
            ],
            'collections' => [
                'total' => $collections->count(),
                'with_descriptions' => $collectionsWithDescriptions->count(),
                'missing_descriptions' => $collections->diff($collectionsWithDescriptions)->pluck('title')->take(10)->values()->all(),
                'priority' => $collections->take(12)->map(fn ($collection) => [
                    'id' => $collection->id,
                    'title' => $collection->title,
                    'url' => $collection->url,
                    'words' => $this->wordCount($collection->description ?: $collection->generated_description),
                ])->values()->all(),
            ],
            'pages' => [
                'total' => $pages->count(),
                'answer_pages' => $answerPages->count(),
                'titles' => $pages->pluck('title')->take(12)->values()->all(),
            ],
            'blogs' => [
                'shopify_total' => $existingBlogs->count(),
                'portal_total' => $localBlogs->count(),
                'published_portal' => $publishedLocalBlogs->count(),
                'with_faqs' => $blogsWithFaqs->count(),
                'with_internal_links' => $blogsWithLinks->count(),
                'published_titles' => $publishedLocalBlogs->pluck('title')->take(10)->values()->all(),
            ],
            'knowledge_base' => [
                'status' => $knowledgeBase?->status,
                'has_summary' => $this->wordCount($knowledgeBase?->summary) >= 50,
                'has_owner_notes' => $this->wordCount($knowledgeBase?->editable_notes) >= 20,
                'brand_profile' => filled($knowledgeBase?->brand_profile),
                'audience_profile' => filled($knowledgeBase?->audience_profile),
            ],
            'store' => [
                'name' => $store->name,
                'domain' => $store->shop_domain,
                'country' => $store->country,
                'language' => $store->default_language,
                'brand_tone' => $store->brand_tone,
                'niche' => $store->latestAnalysis?->niche,
                'audience' => $store->latestAnalysis?->target_audience,
            ],
        ];
    }

    private function promptChecks(ShopifyStore $store): Collection
    {
        $prompts = collect();

        $store->collections
            ->sortByDesc(fn (ShopifyCollection $collection) => (int) ($collection->product_count ?? 0))
            ->take(8)
            ->each(function (ShopifyCollection $collection) use ($store, $prompts): void {
                $descriptionWords = $this->wordCount($collection->description ?: $collection->generated_description);
                $hasFaq = count((array) $collection->generated_faq) > 0 || Str::contains(Str::lower((string) $collection->generated_aeo_content), ['faq', 'question', 'answer']);
                $score = $this->clamp(($descriptionWords >= 80 ? 55 : ($descriptionWords >= 35 ? 30 : 8)) + ($hasFaq ? 25 : 0) + (filled($collection->url) ? 10 : 0));

                $prompts->push($this->promptPayload(
                    prompt: "What should I know before buying {$collection->title} from {$store->name}?",
                    intent: 'buying_guide',
                    entityType: ShopifyCollection::class,
                    entityId: $collection->id,
                    entityLabel: $collection->title,
                    score: $score,
                    evidence: [
                        'description_words' => $descriptionWords,
                        'has_faq' => $hasFaq,
                        'source_url' => $collection->url,
                    ],
                    sourceUrl: $collection->url,
                    recommendation: $score >= 75
                        ? 'Collection has enough source content for an answer-style response.'
                        : 'Add a clear collection intro, buyer questions, benefits, and FAQs before expecting answer-engine visibility.'
                ));
            });

        $store->products
            ->pluck('product_type')
            ->filter()
            ->unique()
            ->take(6)
            ->each(function (string $type) use ($store, $prompts): void {
                $matchingProducts = $store->products->where('product_type', $type);
                $describedProducts = $matchingProducts->filter(fn ($product) => $this->wordCount($product->description) >= 40);
                $publishedBlogs = $store->blogs->filter(fn ($blog) => Str::contains(Str::lower($blog->body.' '.$blog->title), Str::lower($type)));
                $score = $this->percentage($describedProducts->count(), max(1, $matchingProducts->count())) * 0.55
                    + min(35, $publishedBlogs->count() * 12)
                    + 10;

                $prompts->push($this->promptPayload(
                    prompt: "How do I choose the right {$type}?",
                    intent: 'product_education',
                    entityType: 'product_type',
                    entityId: null,
                    entityLabel: $type,
                    score: $this->clamp($score),
                    evidence: [
                        'products' => $matchingProducts->count(),
                        'products_with_descriptions' => $describedProducts->count(),
                        'related_blogs' => $publishedBlogs->count(),
                    ],
                    sourceUrl: null,
                    recommendation: $score >= 75
                        ? 'Product category has enough supporting copy and blog coverage.'
                        : 'Create one product education article and improve descriptions for this product type.'
                ));
            });

        $store->blogs
            ->whereIn('status', [Blog::STATUS_PUBLISHED, Blog::STATUS_APPROVED])
            ->take(8)
            ->each(function (Blog $blog) use ($prompts): void {
                $questionSubject = $blog->primary_keyword ?: $blog->title;
                $bodyWords = $this->wordCount($blog->body);
                $faqCount = count((array) $blog->faq);
                $linkCount = count((array) $blog->internal_links) + count((array) $blog->product_links);
                $score = $this->clamp(($bodyWords >= 900 ? 45 : ($bodyWords >= 500 ? 30 : 10)) + min(25, $faqCount * 7) + min(20, $linkCount * 5) + (filled($blog->published_url) ? 10 : 0));

                $prompts->push($this->promptPayload(
                    prompt: "Can {$questionSubject} help me decide what to buy?",
                    intent: 'commercial_answer',
                    entityType: Blog::class,
                    entityId: $blog->id,
                    entityLabel: $blog->title,
                    score: $score,
                    evidence: [
                        'body_words' => $bodyWords,
                        'faq_count' => $faqCount,
                        'link_count' => $linkCount,
                        'source_url' => $blog->published_url,
                    ],
                    sourceUrl: $blog->published_url,
                    recommendation: $score >= 75
                        ? 'Blog is structured well for answer and buying-intent visibility.'
                        : 'Add a stronger answer-first intro, FAQs, and product links before publishing or republishing.'
                ));
            });

        $store->pages
            ->take(6)
            ->each(function ($page) use ($store, $prompts): void {
                $words = $this->wordCount($page->body ?: $page->summary);
                $answerIntent = Str::contains(Str::lower($page->title), ['faq', 'shipping', 'returns', 'about', 'care', 'contact']);
                $score = $this->clamp(($words >= 250 ? 55 : ($words >= 80 ? 30 : 10)) + ($answerIntent ? 25 : 0) + (filled($page->url) ? 10 : 0));

                $prompts->push($this->promptPayload(
                    prompt: "What does {$store->name} say about {$page->title}?",
                    intent: 'brand_trust',
                    entityType: 'shopify_page',
                    entityId: $page->id,
                    entityLabel: $page->title,
                    score: $score,
                    evidence: [
                        'page_words' => $words,
                        'answer_intent_page' => $answerIntent,
                        'source_url' => $page->url,
                    ],
                    sourceUrl: $page->url,
                    recommendation: $score >= 75
                        ? 'Page gives AI engines enough brand-trust source material.'
                        : 'Expand this page with direct answers, policy details, and entity facts.'
                ));
            });

        if ($prompts->isEmpty()) {
            $prompts->push($this->promptPayload(
                prompt: "What products does {$store->name} sell and who are they best for?",
                intent: 'brand_overview',
                entityType: ShopifyStore::class,
                entityId: $store->id,
                entityLabel: $store->name,
                score: 15,
                evidence: ['reason' => 'No synced collections, products, pages, or blogs were available.'],
                sourceUrl: $store->shop_url,
                recommendation: 'Sync Shopify data and generate the store knowledge base before measuring AEO/GEO visibility.'
            ));
        }

        return $prompts
            ->sortBy(fn (array $prompt): int => ['missing' => 0, 'partial' => 1, 'covered' => 2][$prompt['status']] ?? 1)
            ->values();
    }

    private function scores(ShopifyStore $store, array $snapshot, array $promptStats): array
    {
        $productCoverage = $this->percentage($snapshot['products']['with_descriptions'], max(1, $snapshot['products']['total']));
        $collectionCoverage = $this->percentage($snapshot['collections']['with_descriptions'], max(1, $snapshot['collections']['total']));
        $productSeoCoverage = $this->percentage($snapshot['products']['with_seo'], max(1, $snapshot['products']['total']));
        $blogCoverage = min(100, (($snapshot['blogs']['shopify_total'] + $snapshot['blogs']['published_portal']) / 10) * 100);
        $faqCoverage = min(100, (($snapshot['blogs']['with_faqs'] * 10) + ($snapshot['pages']['answer_pages'] * 15)));
        $knowledgeScore = collect($snapshot['knowledge_base'])->filter(fn ($value) => $value === true || $value === 'completed')->count() * 20;
        $promptCoverageScore = $promptStats['total'] > 0
            ? (($promptStats['covered'] * 100) + ($promptStats['partial'] * 55)) / $promptStats['total']
            : 0;

        $answerCoverage = $this->weighted([
            [$productCoverage, 0.24],
            [$collectionCoverage, 0.24],
            [$blogCoverage, 0.22],
            [$faqCoverage, 0.16],
            [$promptCoverageScore, 0.14],
        ]);
        $contentDepth = $this->weighted([
            [$productCoverage, 0.25],
            [$collectionCoverage, 0.25],
            [$blogCoverage, 0.28],
            [$productSeoCoverage, 0.12],
            [$snapshot['pages']['total'] >= 4 ? 100 : $snapshot['pages']['total'] * 25, 0.10],
        ]);
        $entityConfidence = $this->weighted([
            [filled($store->name) ? 100 : 0, 0.18],
            [filled($store->brand_tone) ? 100 : 0, 0.12],
            [filled($store->country) ? 100 : 0, 0.10],
            [$knowledgeScore, 0.28],
            [$snapshot['pages']['answer_pages'] >= 3 ? 100 : $snapshot['pages']['answer_pages'] * 33, 0.16],
            [$blogCoverage, 0.16],
        ]);
        $schemaReadiness = $this->weighted([
            [$faqCoverage, 0.40],
            [$productSeoCoverage, 0.25],
            [$collectionCoverage, 0.20],
            [$snapshot['blogs']['with_internal_links'] > 0 ? min(100, $snapshot['blogs']['with_internal_links'] * 15) : 0, 0.15],
        ]);

        $aeo = $this->weighted([
            [$answerCoverage, 0.34],
            [$promptCoverageScore, 0.26],
            [$schemaReadiness, 0.20],
            [$contentDepth, 0.20],
        ]);
        $geo = $this->weighted([
            [$entityConfidence, 0.30],
            [$contentDepth, 0.24],
            [$promptCoverageScore, 0.20],
            [$blogCoverage, 0.16],
            [$collectionCoverage, 0.10],
        ]);
        $llm = $this->weighted([
            [$knowledgeScore, 0.30],
            [$entityConfidence, 0.24],
            [$answerCoverage, 0.23],
            [$schemaReadiness, 0.23],
        ]);

        return [
            'answer_coverage_score' => $this->clamp($answerCoverage),
            'entity_confidence_score' => $this->clamp($entityConfidence),
            'content_depth_score' => $this->clamp($contentDepth),
            'schema_readiness_score' => $this->clamp($schemaReadiness),
            'prompt_coverage_score' => $this->clamp($promptCoverageScore),
            'aeo_score' => $this->clamp($aeo),
            'geo_score' => $this->clamp($geo),
            'llm_readiness_score' => $this->clamp($llm),
            'overall_score' => $this->clamp($this->weighted([
                [$aeo, 0.34],
                [$geo, 0.34],
                [$llm, 0.32],
            ])),
        ];
    }

    private function contentGaps(ShopifyStore $store, array $snapshot, array $promptStats, array $scores): array
    {
        return array_values(array_filter([
            $snapshot['knowledge_base']['has_summary'] ? null : 'Store knowledge base needs a stronger summary before AI-answer tracking is reliable.',
            $snapshot['collections']['missing_descriptions'] ? 'Collection pages without descriptions: '.implode(', ', $snapshot['collections']['missing_descriptions']) : null,
            $snapshot['products']['missing_descriptions'] ? 'Products without helpful descriptions: '.implode(', ', $snapshot['products']['missing_descriptions']) : null,
            $snapshot['blogs']['with_faqs'] < 3 ? 'Too few blogs include FAQ sections that can answer direct buyer questions.' : null,
            $snapshot['blogs']['published_portal'] < 5 ? 'Publish more answer-first blogs around priority collections and product questions.' : null,
            $snapshot['pages']['answer_pages'] < 3 ? 'Add or expand trust pages such as FAQ, shipping, returns, care, and about pages.' : null,
            $promptStats['missing'] > 0 ? "{$promptStats['missing']} tracked AI prompts have weak or missing source content." : null,
            $scores['entity_confidence_score'] < 60 ? 'Brand/entity signals are thin; AI engines need clearer facts about the business, audience, location, policies, and expertise.' : null,
        ]));
    }

    private function recommendations(ShopifyStore $store, array $snapshot, array $promptStats, array $scores): array
    {
        return array_values(array_filter([
            'Create answer-first content for the lowest-scoring prompts, starting with collection buying guides.',
            $snapshot['knowledge_base']['has_owner_notes'] ? null : 'Ask the store owner to add brand facts, expertise, customer profile, shipping/care details, and differentiators to the knowledge base.',
            $snapshot['collections']['missing_descriptions'] ? 'Generate SEO and AEO descriptions for missing collection pages, then push them to Shopify.' : null,
            $snapshot['products']['missing_descriptions'] ? 'Generate product descriptions for products missing useful buyer information.' : null,
            $snapshot['blogs']['with_faqs'] < 3 ? 'Add FAQ sections to existing and future blogs using real customer questions.' : null,
            $snapshot['blogs']['with_internal_links'] < 5 ? 'Add internal links from blogs to relevant products, collections, shipping, returns, and care pages.' : null,
            $scores['schema_readiness_score'] < 60 ? 'Add structured FAQ-style content and product metadata before adding advanced schema in a later phase.' : null,
            $promptStats['covered'] >= 8 ? 'Start monitoring these prompts in live AI-answer engines once the paid provider layer is added.' : null,
        ]));
    }

    private function findings(ShopifyStore $store, array $snapshot, array $scores): array
    {
        return [
            [
                'label' => 'Answer coverage',
                'score' => $scores['answer_coverage_score'],
                'detail' => "{$snapshot['products']['with_descriptions']} of {$snapshot['products']['total']} products and {$snapshot['collections']['with_descriptions']} of {$snapshot['collections']['total']} collections have enough source copy.",
            ],
            [
                'label' => 'Prompt coverage',
                'score' => $scores['prompt_coverage_score'],
                'detail' => 'Generated AI-search prompts are scored from currently synced products, collections, blogs, and pages.',
            ],
            [
                'label' => 'Entity confidence',
                'score' => $scores['entity_confidence_score'],
                'detail' => filled($store->latestAnalysis?->niche)
                    ? "Latest niche signal: {$store->latestAnalysis->niche}."
                    : 'Run store analysis and complete the knowledge base to strengthen brand/entity signals.',
            ],
            [
                'label' => 'Structured answer readiness',
                'score' => $scores['schema_readiness_score'],
                'detail' => "{$snapshot['blogs']['with_faqs']} blogs include FAQs and {$snapshot['pages']['answer_pages']} pages look answer-focused.",
            ],
        ];
    }

    private function summary(ShopifyStore $store, array $scores, array $promptStats): string
    {
        $level = $scores['overall_score'] >= 75 ? 'strong' : ($scores['overall_score'] >= 50 ? 'developing' : 'early');

        return "{$store->name} has {$level} AEO/GEO readiness with {$promptStats['covered']} covered prompts, {$promptStats['partial']} partial prompts, and {$promptStats['missing']} missing prompts.";
    }

    private function promptStats(Collection $prompts): array
    {
        return [
            'total' => $prompts->count(),
            'covered' => $prompts->where('status', 'covered')->count(),
            'partial' => $prompts->where('status', 'partial')->count(),
            'missing' => $prompts->where('status', 'missing')->count(),
        ];
    }

    private function promptPayload(string $prompt, string $intent, ?string $entityType, mixed $entityId, ?string $entityLabel, float|int $score, array $evidence, ?string $sourceUrl, string $recommendation): array
    {
        $score = $this->clamp($score);

        return [
            'prompt' => $prompt,
            'intent' => $intent,
            'target_entity_type' => $entityType,
            'target_entity_id' => $entityId,
            'target_entity_label' => $entityLabel,
            'status' => $score >= 75 ? 'covered' : ($score >= 45 ? 'partial' : 'missing'),
            'score' => $score,
            'evidence' => $evidence,
            'recommended_source_url' => $sourceUrl,
            'recommendation' => $recommendation,
        ];
    }

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? min(100, max(0, ($part / $total) * 100)) : 0;
    }

    private function weighted(array $items): float
    {
        $weight = array_sum(array_column($items, 1));

        if ($weight <= 0) {
            return 0;
        }

        return collect($items)->sum(fn (array $item): float => ((float) $item[0]) * ((float) $item[1])) / $weight;
    }

    private function clamp(float|int $value): int
    {
        return (int) round(min(100, max(0, $value)));
    }

    private function wordCount(?string $html): int
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html))));

        return $text === '' ? 0 : str_word_count($text);
    }
}
