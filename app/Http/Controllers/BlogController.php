<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogComment;
use App\Models\ShopifyStore;
use App\Services\BlogGenerationService;
use App\Services\SEOScoringService;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.blogs.index');
        }

        $this->authorize('viewAny', Blog::class);

        $accountId = $request->user()->current_account_id;
        $filters = $request->only(['store', 'status', 'keyword', 'created_from', 'scheduled_from', 'published_from']);

        $blogs = Blog::query()
            ->with('store:id,name,timezone')
            ->forAccount($accountId)
            ->when($filters['store'] ?? null, fn ($query, $store) => $query->where('shopify_store_id', $store))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->where(function ($query) use ($keyword): void {
                $query->where('primary_keyword', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%");
            }))
            ->when($filters['created_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['scheduled_from'] ?? null, fn ($query, $date) => $query->whereDate('scheduled_at', '>=', $date))
            ->when($filters['published_from'] ?? null, fn ($query, $date) => $query->whereDate('published_at', '>=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Blogs/Index', [
            'blogs' => $blogs,
            'stores' => ShopifyStore::forAccount($accountId)->get(['id', 'name', 'timezone']),
            'filters' => $filters,
            'statuses' => [
                Blog::STATUS_DRAFT,
                Blog::STATUS_NEEDS_REVIEW,
                Blog::STATUS_APPROVED,
                Blog::STATUS_SCHEDULED,
                Blog::STATUS_PUBLISHED,
                Blog::STATUS_FAILED,
                Blog::STATUS_REJECTED,
            ],
        ]);
    }

    public function edit(Blog $blog): Response
    {
        $this->authorize('view', $blog);

        return Inertia::render('Blogs/Edit', [
            'blog' => $blog->load(['store:id,name,brand_tone,timezone', 'topic', 'assignee:id,name', 'comments.user:id,name', 'revisions.user:id,name']),
        ]);
    }

    public function update(Request $request, Blog $blog, BlogGenerationService $revisions, SEOScoringService $seo): RedirectResponse
    {
        $this->authorize('update', $blog);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'featured_image_idea' => ['nullable', 'string'],
            'featured_image_prompt' => ['nullable', 'string'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'featured_image_url' => ['nullable', 'string', 'max:1000'],
            'primary_keyword' => ['nullable', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array'],
            'faq' => ['nullable', 'array'],
            'internal_links' => ['nullable', 'array'],
            'product_links' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,needs_review,approved,scheduled,published,failed,rejected'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $validated += $seo->score([...$blog->toArray(), ...$validated]);

        $blog->update($validated);
        $revisions->snapshot($blog->refresh(), $request->user(), 'Manual edit');

        return back()->with('status', 'Blog updated.');
    }

    public function syncFromShopify(Request $request, Blog $blog, ShopifyService $shopify, BlogGenerationService $revisions, SEOScoringService $seo): RedirectResponse
    {
        $this->authorize('update', $blog);

        if (! $blog->shopify_article_id) {
            return back()->withErrors(['sync' => 'Publish this blog first before syncing it back from Shopify.']);
        }

        try {
            $article = $shopify->getArticle($blog->loadMissing('store.credential'));
        } catch (\Throwable $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        $metafields = collect(Arr::get($article, 'metafields.nodes', []))
            ->mapWithKeys(fn (array $metafield) => [$metafield['key'] ?? '' => $metafield['value'] ?? null]);

        $updates = [
            'title' => $article['title'] ?? $blog->title,
            'slug' => $article['handle'] ?? $blog->slug,
            'body' => $article['body'] ?? $blog->body,
            'excerpt' => $article['summary'] ?? $blog->excerpt,
            'meta_title' => $metafields->get('title_tag') ?: $blog->meta_title,
            'meta_description' => $metafields->get('description_tag') ?: $blog->meta_description,
            'shopify_blog_id' => $article['blog_id'] ?? $blog->shopify_blog_id,
            'shopify_article_id' => $article['id'] ?? $blog->shopify_article_id,
            'published_url' => $article['url'] ?? $blog->published_url,
            'published_at' => $article['publishedAt'] ?? $blog->published_at,
            'status' => ($article['isPublished'] ?? false) ? Blog::STATUS_PUBLISHED : Blog::STATUS_DRAFT,
            'failure_message' => null,
        ];

        $updates += $seo->score([...$blog->toArray(), ...$updates]);

        $blog->update($updates);
        $revisions->snapshot($blog->refresh(), $request->user(), 'Synced from Shopify article');

        return back()->with('status', 'Blog synced from Shopify.');
    }

    public function comment(Request $request, Blog $blog): RedirectResponse
    {
        $this->authorize('view', $blog);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        BlogComment::query()->create([
            'account_id' => $blog->account_id,
            'blog_id' => $blog->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('status', 'Comment added.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        $this->authorize('delete', $blog);

        $blog->delete();

        return redirect()->route('blogs.index')->with('status', 'Blog deleted.');
    }
}
