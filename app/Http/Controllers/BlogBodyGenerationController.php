<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Services\BlogGenerationService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BlogBodyGenerationController extends Controller
{
    public function __invoke(Request $request, Blog $blog, BlogGenerationService $blogs): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $blog);
        abort_unless(PlanFeatureGate::moduleAccess($request->user()->currentAccount)['blogs'], 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'featured_image_idea' => ['sometimes', 'nullable', 'string'],
            'primary_keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'secondary_keywords' => ['sometimes', 'nullable', 'array'],
            'tone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'target_word_count' => ['sometimes', 'nullable', 'integer', 'min:300', 'max:1500'],
        ]);

        $blogUpdates = collect($validated)->except('tone')->all();

        if (array_key_exists('target_word_count', $validated)) {
            $blogUpdates['payload'] = [
                ...($blog->payload ?? []),
                'target_word_count' => $validated['target_word_count'],
            ];
            unset($blogUpdates['target_word_count']);
        }

        if ($blogUpdates !== []) {
            $blog->update($blogUpdates);
        }

        try {
            $generatedBlog = $blogs->generateBody($blog, $request->user(), [
                'tone' => $validated['tone'] ?? null,
                'target_word_count' => $validated['target_word_count'] ?? null,
            ]);
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        if ($generatedBlog->generation_status === 'failed') {
            $message = $generatedBlog->failure_message ?: 'Full blog body generation failed.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['body' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Full blog body generated.',
                'body' => $generatedBlog->body,
                'faq' => $generatedBlog->faq ?? [],
                'internal_links' => $generatedBlog->internal_links ?? [],
                'product_links' => $generatedBlog->product_links ?? [],
                'featured_image_idea' => $generatedBlog->featured_image_idea,
                'seo_score' => $generatedBlog->seo_score,
                'readability_score' => $generatedBlog->readability_score,
                'status' => $generatedBlog->status,
                'generation_status' => $generatedBlog->generation_status,
            ]);
        }

        return back()->with('status', 'Full blog body generated.');
    }
}
