<?php

namespace App\Http\Middleware;

use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ((bool) config('services.shopify.manual_connection_mode', true)) {
            return route('login');
        }

        if (! filled(config('services.shopify.public_app_api_key'))) {
            return route('login');
        }

        $shopify = app(ShopifyService::class);
        $shopifyContext = app(ShopifyContext::class);
        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop !== '' && $shopify->isValidShopDomain($shop)) {
            return $shopifyContext->decorate(route('shopify.app', ['shop' => $shop]), $request);
        }

        return route('login');
    }
}
