<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBlogJob;
use App\Jobs\GenerateBlogTopicsJob;
use App\Models\BlogTopic;
use App\Models\AIGeneration;
use App\Models\ShopifyCollection;
use App\Models\ShopifyStore;
use App\Services\BlogGenerationService;
use App\Services\BlogTopicService;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Inertia\Inertia;
use Inertia\Response;

class BlogTopicController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.topics.index');
        }

        $this->authorize('viewAny', BlogTopic::class);

        $accountId = $request->user()->current_account_id;

        $credits = app(CreditService::class);

        return Inertia::render('Topics/Index', [
            'stores' => ShopifyStore::forAccount($accountId)->get(['id', 'name', 'country', 'default_language', 'primary_locale', 'timezone']),
            'credits' => $credits->summary($request->user()->currentAccount),
            'topicCreditCost' => CreditService::TOPIC_CREDITS,
            'collections' => ShopifyCollection::query()
                ->forAccount($accountId)
                ->orderBy('title')
                ->get(['id', 'shopify_store_id', 'title', 'handle']),
            'latestGeneration' => AIGeneration::query()
                ->forAccount($accountId)
                ->where('type', 'topic_generation')
                ->latest()
                ->first(['id', 'shopify_store_id', 'status', 'metadata', 'error_message', 'started_at', 'completed_at']),
            'topics' => BlogTopic::query()
                ->with('store:id,name')
                ->forAccount($accountId)
                ->whereNotIn('status', ['approved', 'rejected'])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'approvedTopics' => BlogTopic::query()
                ->with('store:id,name')
                ->forAccount($accountId)
                ->where('status', 'approved')
                ->latest()
                ->paginate(10, ['*'], 'approved_page')
                ->withQueryString(),
            'rejectedTopics' => BlogTopic::query()
                ->with('store:id,name')
                ->forAccount($accountId)
                ->where('status', 'rejected')
                ->latest()
                ->paginate(10, ['*'], 'rejected_page')
                ->withQueryString(),
        ]);
    }

    public function generate(Request $request, ShopifyStore $store, BlogTopicService $topics, CreditService $credits): RedirectResponse
    {
        $this->authorize('create', BlogTopic::class);
        $this->authorize('view', $store);

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:25'],
            'target_country' => ['nullable', 'string', 'max:64'],
            'target_state' => ['nullable', 'string', 'max:64'],
            'target_city' => ['nullable', 'string', 'max:64'],
            'target_region' => ['nullable', 'string', 'max:64'],
            'target_language' => ['nullable', 'string', 'max:16'],
            'tone' => ['nullable', 'array'],
            'tone.*' => ['string', 'max:64'],
            'seo_focus' => ['nullable', 'string', 'max:255'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'intent' => ['nullable', 'in:informational,commercial,transactional,navigational,comparison,buying_guide,how_to,problem_solution,local_seo,seasonal,faq_answer_engine,product_education'],
        ]);

        $selectedCollections = ShopifyCollection::query()
            ->forAccount($store->account_id)
            ->where('shopify_store_id', $store->id)
            ->whereIn('id', $validated['collection_ids'] ?? [])
            ->get(['id', 'title', 'handle']);

        $validated['collections'] = $selectedCollections->map(fn (ShopifyCollection $collection) => [
            'id' => $collection->id,
            'title' => $collection->title,
            'handle' => $collection->handle,
        ])->values()->all();
        $validated['collection_titles'] = $selectedCollections->pluck('title')->values()->all();
        $validated['target_region'] = $this->targetRegion($validated);
        $validated['tone'] = array_values($validated['tone'] ?? []);
        $validated['intent_label'] = $this->intentLabel($validated['intent'] ?? null);

        try {
            $credits->ensure($store->account, $credits->topicGenerationCost((int) $validated['count']), 'topic generation');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['credits' => $exception->getMessage()]);
        }

        if (app()->environment('local') || config('queue.default') === 'sync') {
            $topics->generate($store, $validated, $request->user());

            return back()->with('status', 'Topic generation completed.');
        }

        GenerateBlogTopicsJob::dispatch($store->id, $validated, $request->user()->id);

        return back()->with('status', 'Topic generation queued.');
    }

    private function targetRegion(array $data): ?string
    {
        $parts = array_filter([
            $data['target_city'] ?? null,
            $data['target_state'] ?? null,
            $data['target_country'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : ($data['target_region'] ?? null);
    }

    private function intentLabel(?string $intent): ?string
    {
        return [
            'informational' => 'Informational',
            'commercial' => 'Commercial',
            'transactional' => 'Transactional',
            'navigational' => 'Navigational',
            'comparison' => 'Comparison',
            'buying_guide' => 'Buying Guide',
            'how_to' => 'How-to',
            'problem_solution' => 'Problem/Solution',
            'local_seo' => 'Local SEO',
            'seasonal' => 'Seasonal',
            'faq_answer_engine' => 'FAQ / Answer Engine',
            'product_education' => 'Product Education',
        ][$intent] ?? $intent;
    }

    public function update(Request $request, BlogTopic $topic): RedirectResponse
    {
        $this->authorize('update', $topic);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'primary_keyword' => ['nullable', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array'],
            'search_intent' => ['nullable', 'string', 'max:255'],
            'suggested_outline' => ['nullable', 'array'],
            'opportunity_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $topic->update($validated);

        return back()->with('status', 'Topic updated.');
    }

    public function approve(Request $request, BlogTopic $topic): RedirectResponse
    {
        $this->authorize('approve', $topic);

        $topic->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($request->boolean('generate_blog')) {
            GenerateBlogJob::dispatch($topic->id, $request->user()->id);
        }

        return back()->with('status', 'Topic approved.');
    }

    public function reject(BlogTopic $topic): RedirectResponse
    {
        $this->authorize('update', $topic);

        $topic->update(['status' => 'rejected']);

        return back()->with('status', 'Topic rejected.');
    }

    public function generateBlog(Request $request, BlogTopic $topic): RedirectResponse
    {
        $this->authorize('approve', $topic);

        if ($topic->status !== 'approved') {
            $topic->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        }

        if (app()->environment('local') || config('queue.default') === 'sync') {
            app(BlogGenerationService::class)->generateFromTopic($topic, $request->user());

            return back()->with('status', 'Blog draft generated.');
        }

        GenerateBlogJob::dispatch($topic->id, $request->user()->id);

        return back()->with('status', 'Blog generation queued.');
    }

    public function generateSelectedBlogs(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', 'exists:blog_topics,id'],
        ]);

        $topics = BlogTopic::query()
            ->with('store')
            ->whereIn('id', $validated['topic_ids'])
            ->get();

        foreach ($topics as $topic) {
            $this->authorize('approve', $topic);

            if ($topic->status !== 'approved') {
                $topic->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            }

            if (app()->environment('local') || config('queue.default') === 'sync') {
                app(BlogGenerationService::class)->generateFromTopic($topic, $request->user());
            } else {
                GenerateBlogJob::dispatch($topic->id, $request->user()->id);
            }
        }

        return back()->with('status', app()->environment('local') || config('queue.default') === 'sync'
            ? 'Selected blog drafts generated.'
            : 'Selected blog drafts queued.');
    }
}
