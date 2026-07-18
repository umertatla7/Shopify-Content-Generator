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
        $embedded = $this->normalizeEmbedded($incoming, $stored);

        $context = array_filter([
            'shop' => $incoming['shop'] ?? $stored['shop'] ?? null,
            'host' => $incoming['host'] ?? $stored['host'] ?? null,
            'embedded' => $embedded,
        ], fn ($value) => $value !== null && $value !== '');

        if ($context !== []) {
            $request->session()->put(self::SESSION_KEY, $context);
        }

        return $context;
    }

    public function props(Request $request): array
    {
        $context = $this->remember($request);
        $embedded = filter_var($context['embedded'] ?? false, FILTER_VALIDATE_BOOL);

        return [
            ...$context,
            'embedded' => $embedded,
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

    public function embeddedAppUrl(Request $request, string $path = '', array $extra = []): ?string
    {
        $host = $this->decodedHost($request);
        $apiKey = (string) config('services.shopify.public_app_api_key', '');

        if ($host === null || $apiKey === '') {
            return null;
        }

        $path = '/'.ltrim($path, '/');
        $context = array_merge($this->remember($request), $extra);
        $context['embedded'] = '1';
        $context = array_filter($context, fn ($value) => $value !== null && $value !== '');

        $query = $context === [] ? '' : '?'.http_build_query($context);

        return sprintf('https://%s/apps/%s%s%s', $host, $apiKey, $path, $query);
    }

    private function extract(Request $request): array
    {
        return [
            'shop' => $request->query('shop', $request->input('shop')),
            'host' => $request->query('host', $request->input('host')),
            'embedded' => $request->query('embedded', $request->input('embedded')),
        ];
    }

    private function normalizeEmbedded(array $incoming, array $stored): ?string
    {
        $raw = $incoming['embedded'] ?? $stored['embedded'] ?? null;

        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }

        if (filled($incoming['host'] ?? null) || filled($stored['host'] ?? null)) {
            return '1';
        }

        return null;
    }

    private function decodedHost(Request $request): ?string
    {
        $context = $this->remember($request);
        $encodedHost = (string) ($context['host'] ?? '');

        if ($encodedHost === '') {
            return null;
        }

        $decodedHost = base64_decode($encodedHost, true);

        if (! is_string($decodedHost) || $decodedHost === '' || str_contains($decodedHost, '://')) {
            return null;
        }

        if (! str_starts_with($decodedHost, 'admin.shopify.com/')) {
            return null;
        }

        return $decodedHost;
    }
}
