<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use App\Services\Shopify\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncShopifyAccountContext
{
    public function __construct(
        private readonly ShopifyService $shopify,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $shop = $this->shopify->normalizeDomain((string) $request->query('shop', ''));

            if ($shop !== '' && $this->shopify->isValidShopDomain($shop)) {
                $store = ShopifyStore::query()
                    ->where('shop_domain', $shop)
                    ->first();

                if (
                    $store
                    && (int) $user->current_account_id !== (int) $store->account_id
                    && $user->belongsToAccount($store->account_id)
                ) {
                    $user->forceFill([
                        'current_account_id' => $store->account_id,
                    ])->save();

                    $user->setRelation('currentAccount', $store->account);
                }
            }
        }

        return $next($request);
    }
}
