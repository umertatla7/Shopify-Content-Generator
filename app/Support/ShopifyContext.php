<?php

namespace App\Support;

use Illuminate\Http\Request;

class ShopifyContext
{
    private const SESSION_KEY = 'shopify_context';

    public function remember(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY, []);
        $incoming = $this->extract($request);

        $context = array_filter([
            'shop' => $incoming['shop'] ?? $stored['shop'] ?? null,
            'host' => $incoming['host'] ?? $stored['host'] ?? null,
            'embedded' => $incoming['embedded'] ?? $stored['embedded'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($context !== []) {
            $request->session()->put(self::SESSION_KEY, $context);
        }

        return $context;
    }

    public function props(Request $request): array
    {
        $context = $this->remember($request);

        return [
            ...$context,
            'embedded' => filter_var($context['embedded'] ?? false, FILTER_VALIDATE_BOOL),
            'public_app_api_key' => config('services.shopify.public_app_api_key'),
            'public_app_url' => rtrim((string) config('services.shopify.public_app_url', config('app.url')), '/'),
            'manual_connection_mode' => (bool) config('services.shopify.manual_connection_mode', true),
        ];
    }

    public function decorate(string $url, Request $request, array $extra = []): string
    {
        $context = array_merge($this->remember($request), $extra);
        $context = array_filter($context, fn ($value) => $value !== null && $value !== '');

        if ($context === []) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        $query = [...$context, ...$query];

        $rebuilt = '';

        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'];

            if (isset($parts['pass'])) {
                $rebuilt .= ':'.$parts['pass'];
            }

            $rebuilt .= '@';
        }

        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';

        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }

    private function extract(Request $request): array
    {
        return [
            'shop' => $request->query('shop'),
            'host' => $request->query('host'),
            'embedded' => $request->query('embedded'),
        ];
    }
}
