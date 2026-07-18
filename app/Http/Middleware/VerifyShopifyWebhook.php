<?php

namespace App\Http\Middleware;

use App\Services\Shopify\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    public function __construct(private readonly ShopifyService $shopify) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.shopify.public_app_client_secret');
        $providedHmac = (string) $request->header('X-Shopify-Hmac-Sha256');
        $shop = strtolower(trim((string) $request->header('X-Shopify-Shop-Domain')));

        if ($secret === '' || $providedHmac === '' || ! $this->shopify->isValidShopDomain($shop)) {
            abort(401, 'Invalid Shopify webhook.');
        }

        $expectedHmac = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($expectedHmac, $providedHmac)) {
            abort(401, 'Invalid Shopify webhook signature.');
        }

        return $next($request);
    }
}
