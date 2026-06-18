<?php

namespace App\Http\Controllers;

use App\Models\ShopifyCollection;
use App\Models\ShopifyStore;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request, CreditService $credits): Response
    {
        $accountId = $request->user()->current_account_id;

        abort_unless($accountId, 403);
        abort_unless($request->user()->hasAccountPermission('stores.view') || $request->user()->hasAccountPermission('stores.manage'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'store' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:any,missing_description,generated,pushed,failed'],
        ]);

        $query = ShopifyCollection::query()
            ->forAccount($accountId)
            ->with('store:id,name,shop_domain,shop_url')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('handle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['store'] ?? null, fn ($query, $store) => $query->where('shopify_store_id', $store))
            ->when(($filters['status'] ?? null) === 'missing_description', fn ($query) => $query->where(function ($query): void {
                $query->whereNull('description')->orWhere('description', '');
            }))
            ->when(($filters['status'] ?? null) === 'generated', fn ($query) => $query->whereNotNull('generated_at'))
            ->when(($filters['status'] ?? null) === 'pushed', fn ($query) => $query->whereNotNull('shopify_pushed_at'))
            ->when(($filters['status'] ?? null) === 'failed', fn ($query) => $query->where('generation_status', 'failed'));

        return Inertia::render('Collections/Index', [
            'collections' => $query->latest('last_synced_at')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'credits' => $credits->summary($request->user()->currentAccount),
            'collectionCreditCosts' => [
                'short' => $credits->collectionContentCost('short'),
                'balanced' => $credits->collectionContentCost('balanced'),
                'long' => $credits->collectionContentCost('long'),
            ],
            'stores' => ShopifyStore::forAccount($accountId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
