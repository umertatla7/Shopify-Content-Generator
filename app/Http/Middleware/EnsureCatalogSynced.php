<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use App\Support\ShopifyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCatalogSynced
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isPlatformAdmin()) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        $account = $user->currentAccount;

        if (! $account) {
            return $next($request);
        }

        $primaryStore = ShopifyStore::query()
            ->forAccount($account->id)
            ->where('status', 'connected')
            ->withCount(['products', 'collections', 'pages', 'existingBlogs'])
            ->latest('id')
            ->first();

        $catalogSynced = $primaryStore
            ? (($primaryStore->products_count + $primaryStore->collections_count + $primaryStore->pages_count + $primaryStore->existing_blogs_count) > 0)
            : false;

        if ($catalogSynced) {
            return $next($request);
        }

        return redirect()->to(app(ShopifyContext::class)->decorate(route('onboarding'), $request))
            ->with('status', 'Sync your Shopify store first to unlock the workspace.');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'shopify.*',
            'onboarding',
            'logout',
            'stores.index',
            'stores.store',
            'stores.sync',
            'stores.destroy',
            'billing.*',
        );
    }
}
