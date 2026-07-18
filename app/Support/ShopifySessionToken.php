<?php

namespace App\Support;

use App\Services\Shopify\ShopifyService;
use Illuminate\Http\Request;
use RuntimeException;

class ShopifySessionToken
{
    public function __construct(
        private readonly ShopifyService $shopify,
    ) {}

    public function fromRequest(Request $request, ?string $expectedShop = null): array
    {
        $token = $this->extractToken($request);

        if ($token === '') {
            throw new RuntimeException('Missing Shopify session token.');
        }

        return $this->validate($token, $expectedShop);
    }

    public function validate(string $token, ?string $expectedShop = null): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid Shopify session token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Unsupported Shopify session token algorithm.');
        }

        $secret = $this->shopify->publicAppClientSecret();

        if ($secret === '') {
            throw new RuntimeException('Shopify public app secret is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true);
        $providedSignature = $this->base64UrlDecode($encodedSignature);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Shopify session token verification failed.');
        }

        $now = time();

        if ((int) ($payload['exp'] ?? 0) < $now) {
            throw new RuntimeException('Shopify session token expired.');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new RuntimeException('Shopify session token is not valid yet.');
        }

        $audience = (string) ($payload['aud'] ?? '');

        if ($audience !== $this->shopify->publicAppApiKey()) {
            throw new RuntimeException('Shopify session token was issued for another app.');
        }

        $shop = $this->shopFromPayload($payload);

        if ($expectedShop && $shop !== $expectedShop) {
            throw new RuntimeException('Shopify session token shop did not match the request.');
        }

        return [
            'shop' => $shop,
            'payload' => $payload,
        ];
    }

    private function extractToken(Request $request): string
    {
        $bearer = (string) $request->bearerToken();

        if ($bearer !== '') {
            return $bearer;
        }

        return (string) ($request->input('id_token') ?: $request->query('id_token', ''));
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($this->base64UrlDecode($value), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid Shopify session token payload.');
        }

        return $decoded;
    }

    private function shopFromPayload(array $payload): string
    {
        $destination = (string) ($payload['dest'] ?? '');
        $host = parse_url($destination, PHP_URL_HOST);

        if (! is_string($host) || ! $this->shopify->isValidShopDomain($host)) {
            throw new RuntimeException('Shopify session token destination is invalid.');
        }

        $issuer = (string) ($payload['iss'] ?? '');
        $issuerHost = parse_url($issuer, PHP_URL_HOST);

        if ($issuerHost !== $host) {
            throw new RuntimeException('Shopify session token issuer did not match its destination.');
        }

        return $host;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if (! is_string($decoded)) {
            throw new RuntimeException('Invalid Shopify session token encoding.');
        }

        return $decoded;
    }
}
