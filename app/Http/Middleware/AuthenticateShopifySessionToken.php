<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use App\Support\ShopifySessionToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateShopifySessionToken
{
    public function __construct(private readonly ShopifySessionToken $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            return $next($request);
        }

        try {
            $session = $this->tokens->fromRequest($request);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $store = ShopifyStore::query()
            ->with(['connectedBy', 'account.owner', 'account.users', 'credential'])
            ->where('shop_domain', $session['shop'])
            ->first();

        if (! $store?->credential?->admin_api_access_token || $store->status !== 'connected') {
            return response()->json(['message' => 'This Shopify store is not connected.'], 401);
        }

        $account = $store->account;
        $user = $store->connectedBy ?: $account?->owner ?: $account?->users->first();

        if (! $account || ! $user || ! $user->belongsToAccount($account)) {
            return response()->json(['message' => 'This Shopify installation has no authorized workspace owner.'], 401);
        }

        $user->forceFill(['current_account_id' => $account->id]);
        $user->setRelation('currentAccount', $account);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('shopify_store', $store);

        return $next($request);
    }
}
