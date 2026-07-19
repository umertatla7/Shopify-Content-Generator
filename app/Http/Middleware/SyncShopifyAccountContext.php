<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncShopifyAccountContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $request->attributes->get('shopify_store');

        if (
            $user
            && $store instanceof ShopifyStore
            && (int) $user->current_account_id !== (int) $store->account_id
            && $user->belongsToAccount($store->account_id)
        ) {
            $user->forceFill([
                'current_account_id' => $store->account_id,
            ])->save();

            $user->setRelation('currentAccount', $store->account);
        }

        return $next($request);
    }
}
