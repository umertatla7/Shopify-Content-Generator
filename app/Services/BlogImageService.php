<?php

namespace App\Services;

use App\Models\AIGeneration;
use App\Models\Blog;
use App\Models\User;
use App\Services\AI\AIProviderService;
use Throwable;

class BlogImageService
{
    public function __construct(
        private readonly AIProviderService $ai,
        private readonly UsageTrackingService $usage,
    ) {}

    public function createBrief(Blog $blog, ?User $user = null): Blog
    {
        $prompt = 'Create a blog featured image generation brief. Return JSON with image_prompt, alt_text, style, recommended_aspect_ratio, notes. '.
            json_encode([
                'title' => $blog->title,
                'primary_keyword' => $blog->primary_keyword,
                'secondary_keywords' => $blog->secondary_keywords,
                'excerpt' => $blog->excerpt,
                'featured_image_idea' => $blog->featured_image_idea,
                'store' => $blog->store?->only(['name', 'brand_tone', 'country', 'default_language']),
            ]);

        $generation = AIGeneration::query()->create([
            'account_id' => $blog->account_id,
            'shopify_store_id' => $blog->shopify_store_id,
            'user_id' => $user?->id,
            'generatable_type' => $blog->getMorphClass(),
            'generatable_id' => $blog->id,
            'type' => 'blog_image',
            'status' => 'running',
            'prompt' => $prompt,
            'started_at' => now(),
        ]);

        $blog->update(['featured_image_status' => 'running']);

        try {
            $result = $this->ai->generate($prompt, [
                'type' => 'blog_image',
                'title' => $blog->title,
                'primary_keyword' => $blog->primary_keyword,
            ]);
            $payload = $this->ai->decodeJson($result['content'], []);

            $blog->update([
                'featured_image_prompt' => $payload['image_prompt'] ?? $blog->featured_image_idea,
                'featured_image_alt' => $payload['alt_text'] ?? $blog->title,
                'featured_image_status' => 'brief_ready',
                'featured_image_payload' => $payload,
                'featured_image_generated_at' => now(),
            ]);

            $generation->update([
                'provider' => $result['provider'],
                'model' => $result['model'],
                'status' => 'completed',
                'response' => $result['content'],
                'token_usage' => $result['usage'],
                'completed_at' => now(),
            ]);

            $this->usage->record($blog->account_id, 'ai_generation', (int) ($result['usage']['total_tokens'] ?? 1), 'token', $blog, $user, [
                'shopify_store_id' => $blog->shopify_store_id,
                'type' => 'blog_image',
            ]);
        } catch (Throwable $exception) {
            $blog->update([
                'featured_image_status' => 'failed',
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
}
