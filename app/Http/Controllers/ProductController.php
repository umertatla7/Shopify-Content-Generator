<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopifyStore;
use App\Services\CreditService;
use App\Services\PlanLimitService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, CreditService $credits, PlanLimitService $planLimits): Response
    {
        $accountId = $request->user()->current_account_id;

        abort_unless($accountId, 403);

        if (! PlanFeatureGate::moduleAccess($request->user()->currentAccount)['products']) {
            return Inertia::render('FeaturePreview', PlanFeatureGate::preview('products'));
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'store' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Product::query()
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
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('product_type', $type))
            ->when($filters['vendor'] ?? null, fn ($query, $vendor) => $query->where('vendor', $vendor));

        return Inertia::render('Products/Index', [
            'products' => $query->latest('last_synced_at')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'credits' => $credits->summary($request->user()->currentAccount),
            'planUsage' => $planLimits->summary($request->user()->currentAccount),
            'productCreditCosts' => [
                'short' => $credits->productContentCost('short'),
                'balanced' => $credits->productContentCost('balanced'),
                'bullets' => $credits->productContentCost('bullets'),
                'long' => $credits->productContentCost('long'),
            ],
            'stores' => ShopifyStore::forAccount($accountId)->orderBy('name')->get(['id', 'name']),
            'filterOptions' => [
                'statuses' => Product::forAccount($accountId)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
                'types' => Product::forAccount($accountId)->whereNotNull('product_type')->distinct()->orderBy('product_type')->pluck('product_type'),
                'vendors' => Product::forAccount($accountId)->whereNotNull('vendor')->distinct()->orderBy('vendor')->pluck('vendor'),
            ],
        ]);
    }
}
