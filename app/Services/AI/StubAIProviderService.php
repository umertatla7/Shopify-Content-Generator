<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class StubAIProviderService implements AIProviderInterface
{
    public function generate(string $prompt, array $options = []): array
    {
        $type = $options['type'] ?? 'generic';
        $title = Str::headline($options['title'] ?? $options['primary_keyword'] ?? 'Shopify SEO Growth');

        $content = match ($type) {
            'store_analysis' => $this->storeAnalysis(),
            'store_knowledge_base' => $this->knowledgeBase(),
            'topic_generation' => $this->topics((int) ($options['count'] ?? 5)),
            'blog_generation' => $this->blog($title, $options['primary_keyword'] ?? 'shopify seo'),
            'blog_body_generation' => $this->blogBody($title, $options['primary_keyword'] ?? 'shopify seo', $options['estimated_article_size'] ?? '1,000 words'),
            'blog_image' => $this->blogImage($title, $options['primary_keyword'] ?? 'shopify seo'),
            'product_content_generation' => $this->productContent($title, $options['description_style'] ?? 'balanced'),
            'rewrite' => $this->rewrite($prompt),
            default => ['summary' => 'Stub AI response generated for local development.'],
        };

        return [
            'content' => json_encode($content, JSON_PRETTY_PRINT),
            'usage' => [
                'prompt_tokens' => str_word_count($prompt),
                'completion_tokens' => 350,
                'total_tokens' => str_word_count($prompt) + 350,
            ],
            'model' => 'stub-local',
            'provider' => 'stub',
        ];
    }

    private function storeAnalysis(): array
    {
        return [
            'niche' => 'Curated ecommerce products',
            'target_audience' => 'Online shoppers looking for trustworthy product guidance and comparison-led recommendations.',
            'brand_voice_summary' => 'Helpful, practical, confident, and lightly conversational.',
            'main_product_categories' => ['Best sellers', 'Seasonal collections', 'Giftable products'],
            'seo_opportunities' => ['Create comparison guides', 'Target long-tail product questions', 'Add seasonal buying guides'],
            'content_gaps' => ['FAQ-led articles', 'Collection buying guides', 'Problem-solution content'],
            'suggested_keywords' => ['best products online', 'how to choose products', 'gift ideas'],
            'suggested_blog_categories' => ['Buying Guides', 'How To', 'Gift Guides'],
            'region_specific_opportunities' => ['Adapt shipping, sizing, and seasonal references by target region.'],
        ];
    }

    private function topics(int $count): array
    {
        return [
            'topics' => collect(range(1, max(1, $count)))->map(fn (int $index) => [
                'title' => "Customer Guide Topic {$index} Based on Store Knowledge",
                'primary_keyword' => "store buying guide {$index}",
                'secondary_keywords' => ["ecommerce guide {$index}", "product tips {$index}"],
                'search_intent' => $index % 2 === 0 ? 'commercial' : 'informational',
                'estimated_article_size' => $index % 2 === 0 ? '1,500-1,800 words' : '1,000-1,300 words',
                'suggested_outline' => ['Introduction', 'What to look for', 'Recommended products', 'FAQs'],
                'related_products' => [],
                'related_collections' => [],
                'opportunity_score' => min(95, 65 + $index),
            ])->all(),
        ];
    }

    private function knowledgeBase(): array
    {
        return [
            'summary' => 'This store sells curated ecommerce products and should publish practical buying guides, collection explainers, and comparison content grounded in synced Shopify product and page data.',
            'brand_profile' => [
                'voice' => 'Helpful, confident, product-aware, and easy to understand.',
                'positioning' => 'A trustworthy store helping shoppers choose the right product with clear guidance.',
            ],
            'audience_profile' => [
                'primary_audience' => 'Shoppers comparing products before purchase.',
                'needs' => ['Product education', 'Confidence before buying', 'Clear benefits and use cases'],
            ],
            'product_insights' => ['Group blogs around best-selling product types and collection-level purchase intent.'],
            'collection_insights' => ['Use collection guides to connect informational content to commercial product discovery.'],
            'content_insights' => ['Add FAQs, product links, and collection CTAs in every article.'],
            'seo_opportunities' => ['Buying guides', 'Gift guides', 'How to choose articles', 'Collection comparison articles'],
        ];
    }

    private function blog(string $title, string $keyword): array
    {
        return [
            'title' => $title,
            'seo_title' => "{$title} | Expert Shopify Guide",
            'meta_title' => "{$title} | Shopify Buying Guide",
            'meta_description' => "Learn how to choose the right products with this SEO-focused guide for {$keyword}.",
            'slug' => Str::slug($title),
            'excerpt' => "A practical guide for shoppers researching {$keyword}.",
            'body' => "<h1>{$title}</h1><p>This draft introduces the topic, explains why it matters, and connects shoppers to relevant products.</p><h2>What to Consider</h2><p>Focus on quality, use case, budget, and customer needs.</p><h2>Recommended Next Steps</h2><p>Compare options, read product details, and choose the best fit.</p>",
            'faq' => [
                ['question' => "What is {$keyword}?", 'answer' => 'It is a topic customers search before deciding what to buy.'],
                ['question' => 'How should I choose?', 'answer' => 'Start with your needs, compare product details, and check trusted guidance.'],
            ],
            'internal_links' => [],
            'product_links' => [],
            'featured_image_idea' => 'A clean lifestyle image showing the product category in use.',
            'primary_keyword' => $keyword,
            'secondary_keywords' => ['buying guide', 'product comparison'],
            'seo_score' => 82,
            'readability_score' => 88,
        ];
    }

    private function blogBody(string $title, string $keyword, string $estimatedSize): array
    {
        $sections = [
            ['Why This Guide Matters', 'Choosing the right product is easier when shoppers understand the materials, use cases, styling options, and long-term value before they buy. This guide explains the main considerations in a practical way and connects each point back to the store collection so readers can move from research to purchase with confidence.'],
            ['What to Look For First', 'Start with the customer need. A shopper may be buying for everyday use, a gift, a special event, or a personal upgrade. Each situation changes what matters most, including durability, design, size, care requirements, and budget. The best article content should help shoppers compare these needs clearly.'],
            ['How to Compare Options', 'Good comparison content does not simply list products. It explains the differences between styles, features, benefits, and common buying mistakes. When the article mentions products, it should describe why a product fits a particular use case and include a natural internal link to the relevant product or collection.'],
            ['Recommended Buying Approach', 'A strong buying approach moves from broad education to specific recommendations. Introduce the category, explain what details matter, show how to evaluate quality, and then guide readers toward the best next step. This structure improves readability and gives the article a clear commercial path.'],
            ['Care and Long-Term Value', 'Many shoppers want reassurance after purchase. Add guidance about care, storage, maintenance, and when to replace or upgrade. This builds trust, reduces hesitation, and makes the article more useful than a short promotional page.'],
            ['Final Thoughts', 'The best choice depends on the shopper, the occasion, and the product details. Use this guide as a starting point, compare the most relevant options, and explore the linked collections to find a product that matches both style and practical needs.'],
        ];

        $body = "<h1>{$title}</h1><p>This SEO article is written for the focus keyword <strong>{$keyword}</strong> and targets an estimated article size of {$estimatedSize}. It is designed to help shoppers understand the category, compare options, and confidently choose products from the store.</p>";

        foreach ($sections as [$heading, $copy]) {
            $body .= "<h2>{$heading}</h2><p>{$copy}</p><p>For best results, review the product descriptions, compare collection details, and choose the option that best matches the shopper's needs. This keeps the article helpful for readers while supporting product discovery and conversion.</p>";
        }

        return [
            'body' => $body,
            'faq' => [
                ['question' => "What should I know before shopping for {$keyword}?", 'answer' => 'Start with your use case, compare product details, and choose based on quality, style, budget, and care requirements.'],
                ['question' => 'How can I choose between similar products?', 'answer' => 'Compare the purpose, materials, size, design, reviews, and collection context before deciding.'],
                ['question' => 'Can this guide help with gifts?', 'answer' => 'Yes. Use the buying criteria to match the product to the recipient, occasion, and preferred style.'],
            ],
            'internal_links' => [],
            'product_links' => [],
            'featured_image_idea' => "A clean editorial hero image for {$title}, showing the product category in a premium lifestyle setting.",
            'seo_score' => 86,
            'readability_score' => 88,
        ];
    }

    private function rewrite(string $prompt): array
    {
        return [
            'body' => "Rewritten draft:\n\n".Str::limit($prompt, 1200),
            'notes' => ['Stub rewrite applied. Configure OPENAI_API_KEY for production responses.'],
        ];
    }

    private function productContent(string $title, string $style): array
    {
        $description = match ($style) {
            'short' => "<p>{$title} is crafted for shoppers who want a polished, easy-to-wear piece with everyday versatility. Its clean design makes it simple to style, gift, and pair with other favorites.</p>",
            'bullets' => "<p>{$title} brings a refined finish to daily styling.</p><ul><li>Easy to pair with casual or occasion outfits</li><li>Gift-ready product copy focused on shopper confidence</li><li>SEO-friendly benefits written in a natural tone</li><li>Clear description structure for fast scanning</li></ul>",
            'long' => "<p>{$title} is designed for customers who want a thoughtful balance of style, quality, and everyday usefulness. This SEO-friendly product description explains the product in a warm, confident way while helping shoppers understand when and how to wear it.</p><p>The piece works well for daily looks, gifting moments, and special occasions because it is easy to pair with different outfits and other accessories. The description focuses on practical benefits, styling ideas, and purchase confidence without sounding overly promotional.</p><p>Use this product when you want a refined option that feels polished, wearable, and simple to understand. It is a strong fit for shoppers comparing pieces before making a confident buying decision.</p>",
            default => "<p>{$title} is a polished, versatile product made for shoppers who want style and confidence in one easy choice. It works well for everyday wear, gifting, and special moments, while the clean design makes it simple to pair with other favorites.</p><p>This SEO-friendly description highlights the product's practical benefits, styling flexibility, and customer value in a clear, natural tone.</p>",
        };

        return [
            'title' => "{$title} - SEO Optimized",
            'description_html' => $description,
            'seo_title' => Str::limit("{$title} | Premium Shopify Product", 70, ''),
            'seo_description' => Str::limit("Shop {$title} with a clear product description, styling ideas, and confidence-building details for online shoppers.", 155, ''),
        ];
    }

    private function blogImage(string $title, string $keyword): array
    {
        return [
            'image_prompt' => "Create a polished editorial Shopify blog hero image for '{$title}'. Show the product category in a bright, realistic lifestyle setting with clean composition, natural light, no text, no logos, and room for cropping.",
            'alt_text' => "Lifestyle image representing {$keyword} for a Shopify blog article.",
            'style' => 'Realistic ecommerce editorial, clean background, natural light',
            'recommended_aspect_ratio' => '16:9',
            'notes' => ['This is an image creation brief. Connect an image provider to generate final pixels.'],
        ];
    }
}
