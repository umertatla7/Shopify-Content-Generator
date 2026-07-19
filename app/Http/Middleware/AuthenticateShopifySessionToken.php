<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifySessionToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateShopifySessionToken
{
    public function __construct(
        private readonly ShopifySessionToken $tokens,
        private readonly ShopifyService $shopify,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        $embedded = $this->hasEmbeddedContext($request);

        if ($bearer === '') {
            if ($embedded && $this->requiresBearerToken($request)) {
                return response()->json(['message' => 'Missing Shopify session token.'], 401);
            }

            if ($embedded && ! $this->isPublicEmbeddedRoute($request) && ! $this->hasVerifiedBrowserContext($request)) {
                $shop = $this->shopify->normalizeDomain((string) $request->query('shop', ''));

                if (! $this->shopify->isValidShopDomain($shop)) {
                    return response(
                        'A valid Shopify shop context is required. Reopen GrowShopHigh from Shopify admin.',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    )->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                }

                return redirect()->route('shopify.app', array_filter([
                    'shop' => $shop,
                    'host' => $request->query('host'),
                    'embedded' => '1',
                ]));
            }

            return $next($request);
        }

        if (! $embedded && substr_count($bearer, '.') !== 2) {
            return $next($request);
        }

        $expectedShop = $this->shopify->normalizeDomain((string) $request->query('shop', ''));

        if (! $this->shopify->isValidShopDomain($expectedShop)) {
            $expectedShop = null;
        }

        try {
            $session = $this->tokens->fromRequest($request, $expectedShop);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $store = ShopifyStore::query()
            ->with(['connectedBy', 'account.owner', 'account.users', 'credential'])
            ->where('shop_domain', $session['shop'])
            ->first();

        if ($store?->status === 'reconnect_required') {
            return response()->json([
                'message' => 'Shopify authorization expired. Reopen the app from Shopify admin to reconnect this store.',
            ], 401);
        }

        if (! $store?->credential?->admin_api_access_token || $store->status !== 'connected') {
            return response()->json(['message' => 'This Shopify store is not connected.'], 401);
        }

        $account = $store->account;
        $user = $store->connectedBy ?: $account?->owner ?: $account?->users->first();

        if (! $account || ! $user || ! $user->belongsToAccount($account)) {
            return response()->json(['message' => 'This Shopify installation has no authorized workspace owner.'], 401);
        }

        $routeStore = $request->route('store');
        $routeStoreId = $routeStore instanceof ShopifyStore
            ? (int) $routeStore->id
            : (is_numeric($routeStore) ? (int) $routeStore : null);

        if ($routeStoreId !== null && $routeStoreId !== (int) $store->id) {
            return response()->json(['message' => 'Shopify session token store did not match the requested store.'], 401);
        }

        if ((int) $user->current_account_id !== (int) $account->id) {
            $user->forceFill(['current_account_id' => $account->id])->save();
        }

        $user->setRelation('currentAccount', $account);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('shopify_store', $store);

        return $next($request);
    }

    private function hasEmbeddedContext(Request $request): bool
    {
        if ($request->boolean('embedded') || filled((string) $request->query('host', ''))) {
            return true;
        }

        if (! $request->hasSession()) {
            return false;
        }

        return filter_var(
            $request->session()->get('shopify_context.embedded', false),
            FILTER_VALIDATE_BOOL,
        );
    }

    private function requiresBearerToken(Request $request): bool
    {
        return $request->is('api/*')
            || $request->expectsJson()
            || $request->headers->has('X-Inertia');
    }

    private function isPublicEmbeddedRoute(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'shopify.app',
            'shopify.session',
            'shopify.install.start',
            'shopify.oauth.callback',
        ], true);
    }

    private function hasVerifiedBrowserContext(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $context = $request->session()->get('shopify_verified_context');

        if (! is_array($context) || (int) ($context['expires_at'] ?? 0) < time()) {
            return false;
        }

        $shop = $this->shopify->normalizeDomain((string) $request->query('shop', ''));

        return $this->shopify->isValidShopDomain($shop)
            && hash_equals((string) ($context['shop'] ?? ''), $shop)
            && hash_equals(
                (string) ($context['host_hash'] ?? ''),
                hash('sha256', (string) $request->query('host', '')),
            )
            && hash_equals(
                (string) ($context['user_agent_hash'] ?? ''),
                hash('sha256', (string) $request->userAgent()),
            )
            && (int) ($context['account_id'] ?? 0) === (int) $request->user()?->current_account_id;
    }
}
