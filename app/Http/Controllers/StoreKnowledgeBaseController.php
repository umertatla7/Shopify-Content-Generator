<?php

namespace App\Http\Controllers;

use App\Models\ShopifyStore;
use App\Services\StoreKnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreKnowledgeBaseController extends Controller
{
    public function show(Request $request, ShopifyStore $store): Response
    {
        $this->authorize('view', $store);

        $store->load([
            'knowledgeBase',
            'pages' => fn ($query) => $query->latest('last_synced_at')->limit(20),
        ])->loadCount(['products', 'collections', 'pages', 'existingBlogs']);

        return Inertia::render('Stores/KnowledgeBase', [
            'store' => $store,
        ]);
    }

    public function generate(Request $request, ShopifyStore $store, StoreKnowledgeBaseService $knowledge): RedirectResponse
    {
        $this->authorize('update', $store);

        $knowledge->generate($store, $request->user());

        return back()->with('status', 'Store knowledge base generated.');
    }

    public function update(Request $request, ShopifyStore $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $validated = $request->validate([
            'summary' => ['nullable', 'string'],
            'editable_notes' => ['nullable', 'string'],
        ]);

        $store->knowledgeBase()->updateOrCreate(
            ['shopify_store_id' => $store->id],
            [
                'account_id' => $store->account_id,
                'status' => 'completed',
                ...$validated,
            ]
        );

        return back()->with('status', 'Knowledge base saved.');
    }
}
