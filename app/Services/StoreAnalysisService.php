<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\ShopifyStore;
use App\Models\StoreAnalysis;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class StoreAnalysisService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly UsageTrackingService $usage,
        private readonly SystemSettingService $settings,
    ) {}

    public function analyze(ShopifyStore $store, ?User $user = null): StoreAnalysis
    {
        $this->extendExecutionLimit();
        $baseAudit = $this->baseAudit($store);
        $prompt = $this->prompt($store, $baseAudit);

        $analysis = StoreAnalysis::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'generated_by' => $user?->id,
            'status' => 'running',
            'prompt' => $prompt,
            'response' => $baseAudit,
        ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'user_id' => $user?->id,
            'generatable_type' => $analysis->getMorphClass(),
            'generatable_id' => $analysis->id,
            'provider' => config('services.ai.provider', 'stub'),
            'model' => config('services.openai.model'),
            'type' => 'store_analysis',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
        ]);

        try {
            $result = $this->ai->generate($prompt, [
                'type' => 'store_analysis',
                'store_name' => $store->name,
            ]);
            $aiPayload = $this->ai->decodeJson($result['content'], []);
            $payload = array_replace_recursive($baseAudit, $aiPayload);
            $payload['performance_report'] = $baseAudit['performance_report'];
            $payload['core_web_vitals'] = $baseAudit['core_web_vitals'];
            $payload['speed_issues'] = array_values(array_unique(array_filter([
                ...Arr::wrap($baseAudit['speed_issues'] ?? []),
                ...Arr::wrap($aiPayload['speed_issues'] ?? []),
            ])));

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $analysis->update([
                'status' => 'completed',
                'niche' => $payload['niche'] ?? null,
                'target_audience' => $payload['target_audience'] ?? null,
                'brand_voice_summary' => $payload['brand_voice_summary'] ?? null,
                'main_product_categories' => $payload['main_product_categories'] ?? [],
                'seo_opportunities' => $payload['seo_opportunities'] ?? [],
                'content_gaps' => $payload['content_gaps'] ?? [],
                'suggested_keywords' => $payload['suggested_keywords'] ?? [],
                'suggested_blog_categories' => $payload['suggested_blog_categories'] ?? [],
                'region_specific_opportunities' => $payload['region_specific_opportunities'] ?? [],
                'response' => $payload,
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->usage->record($store->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $analysis, $user, [
                'shopify_store_id' => $store->id,
                'type' => 'store_analysis',
            ]);
        } catch (Throwable $exception) {
            $payload = [
                ...$baseAudit,
                'ai_enrichment_status' => 'failed',
                'ai_enrichment_error' => $exception->getMessage(),
            ];

            $generation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $analysis->update([
                'status' => 'completed',
                'niche' => $payload['niche'] ?? null,
                'target_audience' => $payload['target_audience'] ?? null,
                'brand_voice_summary' => $payload['brand_voice_summary'] ?? null,
                'main_product_categories' => $payload['main_product_categories'] ?? [],
                'seo_opportunities' => $payload['seo_opportunities'] ?? [],
                'content_gaps' => $payload['content_gaps'] ?? [],
                'suggested_keywords' => $payload['suggested_keywords'] ?? [],
                'suggested_blog_categories' => $payload['suggested_blog_categories'] ?? [],
                'region_specific_opportunities' => $payload['region_specific_opportunities'] ?? [],
                'response' => $payload,
                'error_message' => 'AI enrichment failed; generated rule-based audit. '.$exception->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $analysis->refresh();
    }

    private function prompt(ShopifyStore $store, array $baseAudit): string
    {
        $store->loadMissing(['products', 'collections', 'pages', 'existingBlogs']);

        return 'Analyze this Shopify store for growth, SEO, AEO, content, product, blog, and technical performance. Return JSON with keys: niche, target_audience, main_product_categories, brand_voice_summary, seo_opportunities, content_gaps, suggested_keywords, suggested_blog_categories, region_specific_opportunities, homepage_report, product_page_report, collection_report, blog_report, seo_report, aeo_report, product_audit, blog_audit, technical_audit, core_web_vitals, speed_issues, priority_actions. Keep recommendations practical for a Shopify merchant. Store: '.
            json_encode([
                'name' => $store->name,
                'domain' => $store->shop_domain,
                'country' => $store->country,
                'language' => $store->default_language,
                'brand_tone' => $store->brand_tone,
                'products' => $store->products()->limit(40)->get(['title', 'description', 'product_type', 'vendor'])->toArray(),
                'collections' => $store->collections()->limit(20)->get(['title', 'description'])->toArray(),
                'pages' => $store->pages()->limit(20)->get(['title', 'summary', 'url'])->toArray(),
                'existing_blogs' => $store->existingBlogs()->limit(20)->get(['title', 'summary', 'url'])->toArray(),
                'base_audit' => $baseAudit,
            ]);
    }

    private function baseAudit(ShopifyStore $store): array
    {
        $store->loadMissing(['products', 'collections', 'pages', 'existingBlogs', 'blogs']);

        $products = $store->products;
        $collections = $store->collections;
        $pages = $store->pages;
        $existingBlogs = $store->existingBlogs;
        $localBlogs = $store->blogs;
        $homepage = $this->homepageAudit($store);
        $performance = $this->performanceAudit($store, $homepage);

        $productsMissingDescriptions = $products
            ->filter(fn ($product) => blank(strip_tags((string) $product->description)))
            ->pluck('title')
            ->take(10)
            ->values()
            ->all();

        $productsMissingSeo = $products
            ->filter(fn ($product) => blank($product->seo_title) || blank($product->seo_description))
            ->pluck('title')
            ->take(10)
            ->values()
            ->all();

        $collectionsMissingDescriptions = $collections
            ->filter(fn ($collection) => blank(strip_tags((string) $collection->description)))
            ->pluck('title')
            ->take(10)
            ->values()
            ->all();

        $seoOpportunities = array_values(array_filter([
            $homepage['meta_description'] ? null : 'Add or improve the homepage meta description.',
            ($homepage['h1_count'] ?? 0) === 1 ? null : 'Use exactly one clear H1 on the homepage.',
            $productsMissingSeo ? 'Add SEO titles and meta descriptions to products missing search snippets.' : null,
            $collectionsMissingDescriptions ? 'Write collection descriptions for stronger category SEO.' : null,
            $existingBlogs->count() < 5 ? 'Build a consistent blog library around buying guides, comparisons, and FAQs.' : null,
        ]));

        $speedIssues = array_values(array_filter([
            ($homepage['response_time_ms'] ?? 0) > 1200 ? 'Homepage server response is slow; review theme apps, redirects, and server timing.' : null,
            ($homepage['html_bytes'] ?? 0) > 350000 ? 'Homepage HTML is heavy; reduce app embeds and unused theme sections.' : null,
            ($homepage['script_count'] ?? 0) > 35 ? 'High script count may hurt INP and load speed; audit tracking/apps/scripts.' : null,
            ($homepage['image_without_alt_count'] ?? 0) > 0 ? 'Some homepage images are missing alt text.' : null,
        ]));

        $homepageStatus = $homepage['status'] ?? null;
        $homepageIssues = array_values(array_filter([
            $homepageStatus === 'failed' ? 'Homepage could not be crawled from the saved store URL.' : null,
            is_numeric($homepageStatus) && (int) $homepageStatus >= 400 ? "Homepage returned HTTP {$homepageStatus}." : null,
            blank($homepage['title'] ?? null) ? 'Homepage title tag is missing.' : null,
            blank($homepage['meta_description'] ?? null) ? 'Homepage meta description is missing.' : null,
            ($homepage['h1_count'] ?? 0) === 1 ? null : 'Homepage should have exactly one clear H1.',
            empty($homepage['has_viewport_meta']) ? 'Viewport meta tag is missing, which can hurt mobile rendering.' : null,
            blank($homepage['canonical_url'] ?? null) ? 'Canonical URL is missing on the homepage.' : null,
            ($homepage['image_without_alt_count'] ?? 0) > 0 ? "{$homepage['image_without_alt_count']} homepage image(s) are missing alt text." : null,
            ($homepage['image_without_dimensions_count'] ?? 0) > 0 ? "{$homepage['image_without_dimensions_count']} homepage image(s) are missing width or height attributes." : null,
            ($homepage['script_count'] ?? 0) > 35 ? "{$homepage['script_count']} scripts were found; theme apps may be slowing the site." : null,
        ]));

        $homepageRecommendations = array_values(array_filter([
            'Write a homepage title and meta description around the main commercial keyword and brand promise.',
            ($homepage['h1_count'] ?? 0) === 1 ? null : 'Use one homepage H1 that clearly explains what the store sells.',
            ($homepage['image_without_alt_count'] ?? 0) > 0 ? 'Add descriptive alt text to homepage hero and product images.' : null,
            ($homepage['script_count'] ?? 0) > 35 ? 'Disable unused Shopify apps and scripts before adding more marketing tags.' : null,
            ($homepage['response_time_ms'] ?? 0) > 1200 ? 'Review redirects, app embeds, and theme code that can slow server response.' : null,
        ]));

        $productPageIssues = array_values(array_filter([
            $products->count() === 0 ? 'No products are synced, so product-page SEO cannot be evaluated yet.' : null,
            $productsMissingDescriptions ? count($productsMissingDescriptions).' product(s) are missing useful descriptions.' : null,
            $productsMissingSeo ? count($productsMissingSeo).' product(s) are missing SEO titles or meta descriptions.' : null,
            $products->pluck('product_type')->filter()->count() === 0 ? 'Product types/categories are missing or inconsistent.' : null,
        ]));

        $productPageRecommendations = array_values(array_filter([
            'Generate unique product descriptions that include material, use case, care, size, and buyer-intent language.',
            $productsMissingSeo ? 'Create SEO titles and meta descriptions for products missing snippets.' : null,
            'Add internal links from blog posts to best-selling and category-relevant products.',
            'Use FAQ-style copy on important product pages to improve AEO visibility.',
        ]));

        $collectionIssues = array_values(array_filter([
            $collections->count() === 0 ? 'No collections are synced yet.' : null,
            $collectionsMissingDescriptions ? count($collectionsMissingDescriptions).' collection(s) are missing descriptions.' : null,
        ]));

        $collectionRecommendations = array_values(array_filter([
            $collectionsMissingDescriptions ? 'Add short collection descriptions that target category keywords and buyer questions.' : null,
            'Create buying-guide blog topics around priority collections.',
            'Link collection pages to related educational blog posts and product care content.',
        ]));

        $blogIssues = array_values(array_filter([
            $existingBlogs->count() < 5 ? 'Store has a thin Shopify blog library.' : null,
            $localBlogs->where('status', 'published')->count() === 0 ? 'No portal-generated blogs are published yet.' : null,
            $existingBlogs->count() > 0 && $localBlogs->count() === 0 ? 'Existing Shopify blogs are synced, but no new portal blog workflow has been completed.' : null,
        ]));

        $blogRecommendations = array_values(array_filter([
            'Build a topic cluster for each priority collection using buying guides, comparisons, care guides, and FAQs.',
            'Add FAQ sections and internal product links to every approved article before publishing.',
            $existingBlogs->count() < 5 ? 'Start with 10 foundational articles before moving into long-tail topics.' : 'Refresh older Shopify articles with stronger meta descriptions and product links.',
        ]));

        return [
            'niche' => $this->guessNiche($products, $collections),
            'target_audience' => 'Shopify customers researching products before buying.',
            'brand_voice_summary' => $store->brand_tone ?: 'Helpful, direct, product-aware, and conversion-focused.',
            'main_product_categories' => $products->pluck('product_type')->filter()->unique()->take(10)->values()->all(),
            'seo_opportunities' => $seoOpportunities ?: ['Create SEO content around collections, product comparisons, FAQs, and buying guides.'],
            'content_gaps' => array_values(array_filter([
                $existingBlogs->count() < 5 ? 'Not enough blog content for topical authority.' : null,
                $pages->count() < 3 ? 'Limited static page content synced; add or improve About, FAQ, shipping, and policy pages.' : null,
                $productsMissingDescriptions ? 'Some products need richer descriptions before blog/product link recommendations are strong.' : null,
            ])),
            'suggested_keywords' => $this->keywordIdeas($products, $collections),
            'suggested_blog_categories' => ['Buying Guides', 'Comparison Guides', 'Product Care', 'Gift Guides', 'FAQs'],
            'region_specific_opportunities' => [$store->country ? "Localize examples, shipping language, and seasonal references for {$store->country}." : 'Add region-specific examples based on the target market.'],
            'homepage_report' => [
                'score' => max(0, 100 - (count($homepageIssues) * 10)),
                'url' => $homepage['url'] ?? $store->shop_url,
                'status' => $homepageStatus,
                'title' => $homepage['title'] ?? null,
                'meta_description' => $homepage['meta_description'] ?? null,
                'canonical_url' => $homepage['canonical_url'] ?? null,
                'issues' => $homepageIssues ?: ['No critical crawl-level homepage issue detected.'],
                'recommendations' => $homepageRecommendations,
                'metrics' => [
                    'response_time_ms' => $homepage['response_time_ms'] ?? null,
                    'html_bytes' => $homepage['html_bytes'] ?? null,
                    'h1_count' => $homepage['h1_count'] ?? null,
                    'script_count' => $homepage['script_count'] ?? null,
                    'stylesheet_count' => $homepage['stylesheet_count'] ?? null,
                    'image_count' => $homepage['image_count'] ?? null,
                    'images_missing_alt' => $homepage['image_without_alt_count'] ?? null,
                    'images_missing_dimensions' => $homepage['image_without_dimensions_count'] ?? null,
                ],
            ],
            'product_page_report' => [
                'score' => max(0, 100 - (count($productsMissingDescriptions) * 4) - (count($productsMissingSeo) * 3)),
                'total_products' => $products->count(),
                'issues' => $productPageIssues ?: ['No critical product-page issue detected from synced product data.'],
                'recommendations' => $productPageRecommendations,
                'sample_products_missing_descriptions' => $productsMissingDescriptions,
                'sample_products_missing_seo' => $productsMissingSeo,
            ],
            'collection_report' => [
                'score' => max(0, 100 - (count($collectionsMissingDescriptions) * 8)),
                'total_collections' => $collections->count(),
                'issues' => $collectionIssues ?: ['No critical collection issue detected from synced collection data.'],
                'recommendations' => $collectionRecommendations,
                'sample_collections_missing_descriptions' => $collectionsMissingDescriptions,
            ],
            'blog_report' => [
                'score' => $existingBlogs->count() < 5 ? 55 : 75,
                'synced_shopify_articles' => $existingBlogs->count(),
                'portal_blogs' => $localBlogs->count(),
                'published_portal_blogs' => $localBlogs->where('status', 'published')->count(),
                'issues' => $blogIssues ?: ['Blog library exists; focus on refreshes, internal links, and topical depth.'],
                'recommendations' => $blogRecommendations,
            ],
            'seo_report' => [
                'homepage_title' => $homepage['title'],
                'homepage_meta_description' => $homepage['meta_description'],
                'h1_count' => $homepage['h1_count'],
                'products_missing_descriptions' => $productsMissingDescriptions,
                'products_missing_seo' => $productsMissingSeo,
                'collections_missing_descriptions' => $collectionsMissingDescriptions,
            ],
            'aeo_report' => [
                'score' => $this->aeoScore($existingBlogs->count(), $pages->count(), $productsMissingDescriptions),
                'recommendations' => [
                    'Add concise FAQ blocks to blog posts and collection guides.',
                    'Answer comparison, sizing, care, shipping, and purchase-intent questions directly.',
                    'Use natural question headings that AI answer engines can quote cleanly.',
                ],
            ],
            'product_audit' => [
                'total_products' => $products->count(),
                'missing_descriptions' => count($productsMissingDescriptions),
                'missing_seo_fields' => count($productsMissingSeo),
                'top_product_types' => $products->pluck('product_type')->filter()->countBy()->sortDesc()->take(8)->all(),
            ],
            'blog_audit' => [
                'synced_shopify_articles' => $existingBlogs->count(),
                'portal_blogs' => $localBlogs->count(),
                'published_portal_blogs' => $localBlogs->where('status', 'published')->count(),
                'recommendation' => $existingBlogs->count() < 5 ? 'Publish more educational and commercial-intent articles.' : 'Refresh older articles and add internal product links.',
            ],
            'technical_audit' => $homepage,
            'performance_report' => $performance,
            'core_web_vitals' => $performance['mobile']['core_web_vitals'] ?? $performance['core_web_vitals'],
            'speed_issues' => array_values(array_unique(array_filter([
                ...Arr::wrap($performance['mobile']['issues'] ?? $performance['issues'] ?? []),
                ...Arr::wrap($performance['desktop']['issues'] ?? []),
                ...($speedIssues ?: []),
            ]))) ?: ['No major speed issue detected from available performance data.'],
            'priority_actions' => array_values(array_filter([
                $productsMissingSeo ? 'Generate missing product SEO titles and meta descriptions.' : null,
                $collectionsMissingDescriptions ? 'Generate descriptions for important collections.' : null,
                $existingBlogs->count() < 5 ? 'Create a 10-article SEO blog plan from store knowledge.' : null,
                (($performance['mobile']['score'] ?? $performance['score'] ?? 100) < 90 || ($performance['desktop']['score'] ?? 100) < 90 || $speedIssues) ? 'Fix performance issues before scaling paid acquisition.' : null,
            ])),
        ];
    }

    private function performanceAudit(ShopifyStore $store, array $homepage): array
    {
        $mobileFallback = $this->crawlPerformanceEstimate($homepage, 'mobile');
        $desktopFallback = $this->crawlPerformanceEstimate($homepage, 'desktop');
        $reports = [
            'mobile' => $mobileFallback,
            'desktop' => $desktopFallback,
        ];

        if (! config('services.pagespeed.enabled', true)) {
            return [
                ...$mobileFallback,
                'mobile' => $reports['mobile'],
                'desktop' => $reports['desktop'],
                'source' => 'crawl_estimate',
                'source_label' => 'Crawl estimate',
                'note' => 'PageSpeed Insights is disabled. Scores are estimated from crawl signals only.',
            ];
        }

        foreach (['mobile', 'desktop'] as $strategy) {
            try {
                $timeout = max(5, min((int) config('services.pagespeed.timeout', 45), 12));
                $query = [
                    'url' => $store->shop_url,
                    'strategy' => $strategy,
                    'category' => 'performance',
                ];

                $apiKey = $this->settings->get('pagespeed_insights_api_key', config('services.pagespeed.api_key'));

                if ($apiKey) {
                    $query['key'] = $apiKey;
                }

                $response = Http::connectTimeout(5)
                    ->timeout($timeout)
                    ->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', $query);

                if ($response->failed()) {
                    throw new \RuntimeException($response->json('error.message') ?? $response->body());
                }

                $reports[$strategy] = $this->pageSpeedReport($response->json(), $reports[$strategy], $strategy);
            } catch (Throwable $exception) {
                $reports[$strategy] = [
                    ...$reports[$strategy],
                    'source' => 'crawl_estimate',
                    'source_label' => 'Crawl estimate',
                    'error' => $exception->getMessage(),
                    'note' => $apiKey
                        ? ucfirst($strategy).' PageSpeed Insights was temporarily unavailable, so these values are estimated from crawl signals.'
                        : ucfirst($strategy).' PageSpeed Insights was not available, so these values are estimated from crawl signals. Add PAGESPEED_INSIGHTS_API_KEY for more reliable data.',
                ];
            }
        }

        return [
            ...$reports['mobile'],
            'mobile' => $reports['mobile'],
            'desktop' => $reports['desktop'],
            'source' => $reports['mobile']['source'],
            'source_label' => $reports['mobile']['source_label'],
            'note' => 'Mobile and desktop are shown separately. If PageSpeed is unavailable, values are marked as crawl estimates.',
        ];
    }

    private function extendExecutionLimit(int $seconds = 120): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    private function pageSpeedReport(array $payload, array $fallback, string $strategy): array
    {
        $audits = Arr::get($payload, 'lighthouseResult.audits', []);
        $categories = Arr::get($payload, 'lighthouseResult.categories', []);
        $fieldMetrics = Arr::get($payload, 'loadingExperience.metrics', [])
            ?: Arr::get($payload, 'originLoadingExperience.metrics', []);
        $score = $this->categoryScore(Arr::get($categories, 'performance.score'));
        $seoScore = $this->categoryScore(Arr::get($categories, 'seo.score'));
        $bestPracticeScore = $this->categoryScore(Arr::get($categories, 'best-practices.score'));

        $metrics = [
            'first_contentful_paint_ms' => $this->auditNumeric($audits, 'first-contentful-paint'),
            'largest_contentful_paint_ms' => $this->fieldPercentile($fieldMetrics, 'LARGEST_CONTENTFUL_PAINT_MS') ?: $this->auditNumeric($audits, 'largest-contentful-paint'),
            'interaction_to_next_paint_ms' => $this->fieldPercentile($fieldMetrics, 'INTERACTION_TO_NEXT_PAINT'),
            'total_blocking_time_ms' => $this->auditNumeric($audits, 'total-blocking-time'),
            'cumulative_layout_shift' => $this->fieldPercentile($fieldMetrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE')
                ? round($this->fieldPercentile($fieldMetrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE') / 100, 3)
                : $this->auditNumeric($audits, 'cumulative-layout-shift'),
            'speed_index_ms' => $this->auditNumeric($audits, 'speed-index'),
            'server_response_time_ms' => $this->auditNumeric($audits, 'server-response-time') ?: ($fallback['metrics']['server_response_time_ms'] ?? null),
        ];

        $metrics = array_filter($metrics, fn ($value) => $value !== null);

        return [
            'source' => 'pagespeed_insights',
            'source_label' => 'PageSpeed Insights',
            'strategy' => $strategy,
            'strategy_label' => ucfirst($strategy),
            'final_url' => Arr::get($payload, 'lighthouseResult.finalUrl', Arr::get($payload, 'id')),
            'fetched_at' => $payload['analysisUTCTimestamp'] ?? null,
            'score' => $score,
            'seo_score' => $seoScore,
            'best_practices_score' => $bestPracticeScore,
            'status' => $this->scoreStatus($score),
            'metrics' => $metrics,
            'metric_statuses' => [
                'first_contentful_paint' => $this->timingStatus($metrics['first_contentful_paint_ms'] ?? null, 1800, 3000),
                'largest_contentful_paint' => $this->timingStatus($metrics['largest_contentful_paint_ms'] ?? null, 2500, 4000),
                'interaction_to_next_paint' => $this->timingStatus($metrics['interaction_to_next_paint_ms'] ?? null, 200, 500),
                'total_blocking_time' => $this->timingStatus($metrics['total_blocking_time_ms'] ?? null, 200, 600),
                'cumulative_layout_shift' => $this->clsStatus($metrics['cumulative_layout_shift'] ?? null),
                'server_response_time' => $this->timingStatus($metrics['server_response_time_ms'] ?? null, 800, 1800),
            ],
            'core_web_vitals' => $this->coreWebVitalsFromPerformance($score, $metrics, 'PageSpeed Insights lab/field data'),
            'issues' => $this->pageSpeedIssues($score, $metrics, $seoScore, $bestPracticeScore),
            'recommendations' => $this->pageSpeedRecommendations($score, $metrics),
            'note' => 'Performance score comes from Google PageSpeed Insights. INP is shown from field data when available; otherwise Total Blocking Time is used as a lab proxy.',
        ];
    }

    private function crawlPerformanceEstimate(array $homepage, string $strategy = 'mobile'): array
    {
        $score = 100;
        $mobilePenalty = $strategy === 'mobile' ? 1.25 : 0.8;
        $score -= (($homepage['response_time_ms'] ?? 0) > 800 ? 18 : 0) * $mobilePenalty;
        $score -= (($homepage['response_time_ms'] ?? 0) > 1800 ? 12 : 0) * $mobilePenalty;
        $score -= (($homepage['html_bytes'] ?? 0) > 350000 ? 12 : 0) * $mobilePenalty;
        $score -= (($homepage['script_count'] ?? 0) > 35 ? 18 : 0) * $mobilePenalty;
        $score -= (($homepage['script_count'] ?? 0) > 60 ? 10 : 0) * $mobilePenalty;
        $score -= (($homepage['image_without_dimensions_count'] ?? 0) > 0 ? 10 : 0) * $mobilePenalty;
        $score = max(0, min(100, $score));
        $estimatedLcp = $this->estimatedLcpMs($homepage, $strategy);
        $estimatedInp = $this->estimatedInpMs($homepage, $strategy);
        $estimatedCls = $this->estimatedCls($homepage);
        $metrics = [
            'server_response_time_ms' => $homepage['response_time_ms'] ?? null,
            'largest_contentful_paint_ms' => $estimatedLcp,
            'interaction_to_next_paint_ms' => $estimatedInp,
            'cumulative_layout_shift' => $estimatedCls,
            'html_bytes' => $homepage['html_bytes'] ?? null,
            'script_count' => $homepage['script_count'] ?? null,
            'image_count' => $homepage['image_count'] ?? null,
            'images_missing_dimensions' => $homepage['image_without_dimensions_count'] ?? null,
        ];

        return [
            'source' => 'crawl_estimate',
            'source_label' => 'Crawl estimate',
            'strategy' => $strategy,
            'strategy_label' => ucfirst($strategy),
            'score' => $score,
            'status' => $this->scoreStatus($score),
            'metrics' => $metrics,
            'metric_statuses' => [
                'server_response_time' => $this->timingStatus($homepage['response_time_ms'] ?? null, 800, 1800),
                'largest_contentful_paint' => $this->timingStatus($estimatedLcp, 2500, 4000),
                'interaction_to_next_paint' => $this->timingStatus($estimatedInp, 200, 500),
                'cumulative_layout_shift' => $this->clsStatus($estimatedCls),
            ],
            'core_web_vitals' => $this->coreWebVitalsFromPerformance($score, $metrics, 'Crawl-based estimate'),
            'issues' => array_values(array_filter([
                ($homepage['response_time_ms'] ?? 0) > 800 ? 'Server response is slow for the homepage crawl.' : null,
                ($homepage['html_bytes'] ?? 0) > 350000 ? 'Homepage HTML is heavy before images and scripts are counted.' : null,
                ($homepage['script_count'] ?? 0) > 35 ? 'High script count suggests app/theme JavaScript risk.' : null,
                ($homepage['image_without_dimensions_count'] ?? 0) > 0 ? 'Images missing width/height can increase layout shift risk.' : null,
            ])),
            'recommendations' => [
                'Use PageSpeed Insights for final lab and field data.',
                'Audit Shopify apps, theme scripts, and unused embeds.',
                'Compress hero images and add explicit image dimensions.',
            ],
            'note' => ucfirst($strategy).' values are estimated from crawled HTML, response time, script count, and image markup because PageSpeed data was not available.',
        ];
    }

    private function estimatedLcpMs(array $homepage, string $strategy): int
    {
        $multiplier = $strategy === 'mobile' ? 1.35 : 0.85;
        $base = 1400;
        $base += min(1800, (($homepage['html_bytes'] ?? 0) / 1024) * 3);
        $base += min(900, ($homepage['image_count'] ?? 0) * 18);
        $base += min(900, ($homepage['response_time_ms'] ?? 0) * 0.35);

        return (int) round($base * $multiplier);
    }

    private function estimatedInpMs(array $homepage, string $strategy): int
    {
        $multiplier = $strategy === 'mobile' ? 1.4 : 0.75;
        $base = 90;
        $base += min(520, ($homepage['script_count'] ?? 0) * 7);
        $base += min(220, ($homepage['stylesheet_count'] ?? 0) * 10);

        return (int) round($base * $multiplier);
    }

    private function estimatedCls(array $homepage): float
    {
        $missingDimensions = (int) ($homepage['image_without_dimensions_count'] ?? 0);

        return round(min(0.35, $missingDimensions * 0.018), 3);
    }

    private function coreWebVitalsFromPerformance(?int $score, array $metrics, string $source): array
    {
        return [
            'performance_score' => $score,
            'source' => $source,
            'ttfb_risk' => $this->timingStatus($metrics['server_response_time_ms'] ?? null, 800, 1800),
            'lcp_risk' => $this->timingStatus($metrics['largest_contentful_paint_ms'] ?? null, 2500, 4000),
            'inp_risk' => $this->timingStatus($metrics['interaction_to_next_paint_ms'] ?? ($metrics['total_blocking_time_ms'] ?? null), 200, 500),
            'cls_risk' => $this->clsStatus($metrics['cumulative_layout_shift'] ?? null),
            'note' => "{$source}. Green means good, amber means needs improvement, and red means poor.",
        ];
    }

    private function pageSpeedIssues(?int $score, array $metrics, ?int $seoScore, ?int $bestPracticeScore): array
    {
        return array_values(array_filter([
            $score !== null && $score < 50 ? 'Performance score is poor and needs urgent theme/app optimization.' : null,
            $score !== null && $score >= 50 && $score < 90 ? 'Performance score needs improvement before scaling traffic.' : null,
            ($metrics['largest_contentful_paint_ms'] ?? 0) > 2500 ? 'Largest Contentful Paint is slower than the recommended threshold.' : null,
            (($metrics['interaction_to_next_paint_ms'] ?? $metrics['total_blocking_time_ms'] ?? 0) > 200) ? 'Interaction responsiveness needs attention.' : null,
            ($metrics['cumulative_layout_shift'] ?? 0) > 0.1 ? 'Layout shift is above the recommended threshold.' : null,
            ($metrics['server_response_time_ms'] ?? 0) > 800 ? 'Server response time is slower than recommended.' : null,
            $seoScore !== null && $seoScore < 90 ? 'PageSpeed SEO checks found issues on the homepage.' : null,
            $bestPracticeScore !== null && $bestPracticeScore < 90 ? 'Best-practices checks found theme or browser-quality issues.' : null,
        ]));
    }

    private function pageSpeedRecommendations(?int $score, array $metrics): array
    {
        return array_values(array_filter([
            ($metrics['server_response_time_ms'] ?? 0) > 800 ? 'Improve server response by reducing app embeds, redirects, and expensive theme code.' : null,
            ($metrics['largest_contentful_paint_ms'] ?? 0) > 2500 ? 'Optimize the hero/LCP element with compressed images, preloading, and lean above-the-fold sections.' : null,
            (($metrics['interaction_to_next_paint_ms'] ?? $metrics['total_blocking_time_ms'] ?? 0) > 200) ? 'Remove unused JavaScript and defer non-critical apps to improve responsiveness.' : null,
            ($metrics['cumulative_layout_shift'] ?? 0) > 0.1 ? 'Add fixed dimensions for images, banners, review widgets, and app blocks to reduce layout shift.' : null,
            $score !== null && $score >= 90 ? 'Maintain the current speed profile while adding content or Shopify apps.' : null,
        ])) ?: ['Keep monitoring PageSpeed after theme changes, new apps, and image-heavy content updates.'];
    }

    private function categoryScore(mixed $score): ?int
    {
        if (! is_numeric($score)) {
            return null;
        }

        return (int) round((float) $score * 100);
    }

    private function auditNumeric(array $audits, string $key): ?float
    {
        $value = Arr::get($audits, "{$key}.numericValue");

        return is_numeric($value) ? round((float) $value, 3) : null;
    }

    private function fieldPercentile(array $metrics, string $key): ?float
    {
        $value = Arr::get($metrics, "{$key}.percentile");

        return is_numeric($value) ? (float) $value : null;
    }

    private function timingStatus(float|int|null $value, int $good, int $poor): string
    {
        if ($value === null) {
            return 'needs_lab_test';
        }

        if ($value <= $good) {
            return 'good';
        }

        return $value <= $poor ? 'needs_improvement' : 'poor';
    }

    private function clsStatus(float|int|null $value): string
    {
        if ($value === null) {
            return 'needs_lab_test';
        }

        if ($value <= 0.1) {
            return 'good';
        }

        return $value <= 0.25 ? 'needs_improvement' : 'poor';
    }

    private function scoreStatus(?int $score): string
    {
        if ($score === null) {
            return 'needs_lab_test';
        }

        if ($score >= 90) {
            return 'good';
        }

        return $score >= 50 ? 'needs_improvement' : 'poor';
    }

    private function homepageAudit(ShopifyStore $store): array
    {
        $started = microtime(true);

        try {
            $response = Http::timeout(15)->get($store->shop_url);
            $html = (string) $response->body();
        } catch (Throwable $exception) {
            return [
                'url' => $store->shop_url,
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $images = $dom->getElementsByTagName('img');
        $missingAlt = 0;
        $missingDimensions = 0;

        foreach ($images as $image) {
            if (blank($image->getAttribute('alt'))) {
                $missingAlt++;
            }

            if (blank($image->getAttribute('width')) || blank($image->getAttribute('height'))) {
                $missingDimensions++;
            }
        }

        return [
            'url' => $store->shop_url,
            'status' => $response->status(),
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'html_bytes' => strlen($html),
            'title' => $this->firstTagText($dom, 'title'),
            'meta_description' => $this->metaContent($dom, 'description'),
            'has_viewport_meta' => filled($this->metaContent($dom, 'viewport')),
            'h1_count' => $dom->getElementsByTagName('h1')->length,
            'script_count' => $dom->getElementsByTagName('script')->length,
            'stylesheet_count' => $this->stylesheetCount($dom),
            'image_count' => $images->length,
            'image_without_alt_count' => $missingAlt,
            'image_without_dimensions_count' => $missingDimensions,
            'canonical_url' => $this->canonicalUrl($dom),
        ];
    }

    private function firstTagText(\DOMDocument $dom, string $tag): ?string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length ? trim($nodes->item(0)?->textContent ?? '') : null;
    }

    private function metaContent(\DOMDocument $dom, string $name): ?string
    {
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === strtolower($name)) {
                return trim($meta->getAttribute('content')) ?: null;
            }
        }

        return null;
    }

    private function stylesheetCount(\DOMDocument $dom): int
    {
        $count = 0;

        foreach ($dom->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'stylesheet') {
                $count++;
            }
        }

        return $count;
    }

    private function canonicalUrl(\DOMDocument $dom): ?string
    {
        foreach ($dom->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical') {
                return trim($link->getAttribute('href')) ?: null;
            }
        }

        return null;
    }

    private function guessNiche($products, $collections): string
    {
        $type = $products->pluck('product_type')->filter()->countBy()->sortDesc()->keys()->first();
        $collection = $collections->pluck('title')->filter()->first();

        return $type ?: ($collection ? "{$collection} ecommerce" : 'Shopify ecommerce');
    }

    private function keywordIdeas($products, $collections): array
    {
        $types = $products->pluck('product_type')->filter()->unique()->take(6);
        $collectionTitles = $collections->pluck('title')->filter()->unique()->take(6);

        return $types
            ->merge($collectionTitles)
            ->map(fn (string $keyword) => Str::of($keyword)->lower()->append(' buying guide')->toString())
            ->values()
            ->all();
    }

    private function aeoScore(int $blogCount, int $pageCount, array $missingDescriptions): int
    {
        $score = 55;
        $score += min(20, $blogCount * 3);
        $score += min(10, $pageCount * 2);
        $score -= min(20, count($missingDescriptions) * 2);

        return max(0, min(100, $score));
    }
}
