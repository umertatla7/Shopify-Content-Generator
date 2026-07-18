<?php

namespace App\Http\Middleware;

use App\Support\CatalogAccess;
use App\Support\PlanFeatureGate;
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

        if ($this->isDisabledFeaturePreviewRoute($request, $account)) {
            return $next($request);
        }

        if (CatalogAccess::hasSyncedCatalog($account)) {
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
            'support.*',
        );
    }

    private function isDisabledFeaturePreviewRoute(Request $request, mixed $account): bool
    {
        $routeFeature = match (true) {
            $request->routeIs('products.index') => 'products',
            $request->routeIs('collections.index') => 'collections',
            $request->routeIs('topics.index') => 'topics',
            $request->routeIs('blogs.index') => 'blogs',
            $request->routeIs('store-audit.index') => 'store_audit',
            $request->routeIs('rank-tracking.index') => 'rank_tracking',
            $request->routeIs('visibility.index') => 'ai_visibility',
            default => null,
        };

        if (! $routeFeature) {
            return false;
        }

        return ! (bool) (PlanFeatureGate::moduleAccess($account)[$routeFeature] ?? false);
    }
}
