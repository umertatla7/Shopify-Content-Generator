<?php

namespace App\Http\Controllers;

use App\Jobs\PublishBlogJob;
use App\Models\Blog;
use App\Services\BlogPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogPublishController extends Controller
{
    public function publish(Request $request, Blog $blog, BlogPublishingService $publisher): RedirectResponse
    {
        $this->authorize('publish', $blog);

        if (! $blog->canPublish()) {
            return back()->withErrors(['publish' => 'Approve the blog before publishing it to Shopify.']);
        }

        if (! $blog->hasPublishableBody()) {
            return back()->withErrors(['publish' => 'Add and save blog body content before publishing to Shopify.']);
        }

        if (! $this->shouldQueuePublishing()) {
            $this->extendExecutionLimit();
            $published = $publisher->publish($blog->loadMissing('store.credential'), $request->user());

            return back()->with('status', $published->status === Blog::STATUS_PUBLISHED
                ? ($blog->shopify_article_id ? 'Blog updated on Shopify.' : 'Blog published to Shopify.')
                : 'Publishing failed: '.$published->failure_message);
        }

        PublishBlogJob::dispatch($blog->id, $request->user()->id);

        return back()->with('status', 'Publishing queued.');
    }

    public function publishSelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blog_ids' => ['required', 'array', 'min:1'],
            'blog_ids.*' => ['integer', 'exists:blogs,id'],
        ]);

        $blogs = Blog::query()
            ->whereIn('id', $validated['blog_ids'])
            ->where('account_id', $request->user()->current_account_id)
            ->get();

        $published = 0;
        $skipped = 0;
        $blank = 0;

        foreach ($blogs as $blog) {
            $this->authorize('publish', $blog);

            if (! $blog->canPublish()) {
                $skipped++;
                continue;
            }

            if (! $blog->hasPublishableBody()) {
                $blank++;
                continue;
            }

            if (! $this->shouldQueuePublishing()) {
                $this->extendExecutionLimit();
                app(BlogPublishingService::class)->publish($blog->loadMissing('store.credential'), $request->user());
            } else {
                PublishBlogJob::dispatch($blog->id, $request->user()->id);
            }

            $published++;
        }

        $message = ! $this->shouldQueuePublishing()
            ? "{$published} selected blogs published."
            : "{$published} selected blogs sent for publishing.";

        if ($skipped) {
            $message .= " {$skipped} skipped because they are not approved.";
        }

        if ($blank) {
            $message .= " {$blank} skipped because they do not have saved body content.";
        }

        return back()->with('status', $message);
    }

    public function publishAllApproved(Request $request): RedirectResponse
    {
        $blogs = Blog::query()
            ->where('account_id', $request->user()->current_account_id)
            ->where('status', Blog::STATUS_APPROVED)
            ->get();

        foreach ($blogs as $blog) {
            $this->authorize('publish', $blog);

            if (! $blog->hasPublishableBody()) {
                continue;
            }

            if (! $this->shouldQueuePublishing()) {
                $this->extendExecutionLimit();
                app(BlogPublishingService::class)->publish($blog->loadMissing('store.credential'), $request->user());
            } else {
                PublishBlogJob::dispatch($blog->id, $request->user()->id);
            }
        }

        return back()->with('status', ! $this->shouldQueuePublishing()
            ? $blogs->count().' approved blogs published.'
            : $blogs->count().' approved blogs sent for publishing.');
    }

    private function shouldQueuePublishing(): bool
    {
        return config('services.blog_publishing.via_queue', false)
            && ! app()->environment('local')
            && config('queue.default') !== 'sync';
    }

    private function extendExecutionLimit(int $seconds = 120): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }
}
