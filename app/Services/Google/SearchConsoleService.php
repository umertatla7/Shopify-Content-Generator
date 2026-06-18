<?php

namespace App\Services\Google;

use App\Models\Account;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\KeywordPositionSnapshot;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleProperty;
use App\Models\ShopifyStore;
use App\Models\TrackedKeyword;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SearchConsoleService
{
    public const READONLY_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function authorizationUrl(string $state): string
    {
        $clientId = config('services.google_search_console.client_id');

        if (! $clientId || ! config('services.google_search_console.client_secret')) {
            throw new RuntimeException('Google Search Console OAuth credentials are not configured.');
        }

        return config('services.google_search_console.auth_url').'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google_search_console.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                self::READONLY_SCOPE,
                'https://www.googleapis.com/auth/userinfo.email',
            ]),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->timeout((int) config('services.google_search_console.timeout', 45))
            ->post(config('services.google_search_console.token_url'), [
                'client_id' => config('services.google_search_console.client_id'),
                'client_secret' => config('services.google_search_console.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.google_search_console.redirect_uri'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error_description') ?? $response->json('error') ?? 'Google authorization failed.');
        }

        return $response->json();
    }

    public function upsertConnection(Account $account, User $user, array $tokenPayload): SearchConsoleConnection
    {
        $existing = SearchConsoleConnection::query()
            ->forAccount($account)
            ->latest()
            ->first();

        $accessToken = $tokenPayload['access_token'] ?? null;

        if (! $accessToken) {
            throw new RuntimeException('Google did not return an access token.');
        }

        $refreshToken = $tokenPayload['refresh_token'] ?? $existing?->refresh_token;
        $googleEmail = $this->fetchGoogleEmail($accessToken);

        $attributes = [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'google_email' => $googleEmail,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => $tokenPayload['token_type'] ?? 'Bearer',
            'scopes' => isset($tokenPayload['scope'])
                ? preg_split('/\s+/', trim((string) $tokenPayload['scope']))
                : [self::READONLY_SCOPE],
            'expires_at' => now()->addSeconds((int) ($tokenPayload['expires_in'] ?? 3600)),
            'status' => 'connected',
            'error_message' => null,
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return SearchConsoleConnection::query()->create($attributes);
    }

    public function refreshIfNeeded(SearchConsoleConnection $connection): SearchConsoleConnection
    {
        if ($connection->expires_at && $connection->expires_at->greaterThan(now()->addMinutes(5))) {
            return $connection;
        }

        if (! $connection->refresh_token) {
            $connection->update([
                'status' => 'needs_reconnect',
                'error_message' => 'Google refresh token is missing. Reconnect Search Console.',
            ]);

            throw new RuntimeException('Google Search Console needs to be reconnected.');
        }

        $response = Http::asForm()
            ->timeout((int) config('services.google_search_console.timeout', 45))
            ->post(config('services.google_search_console.token_url'), [
                'client_id' => config('services.google_search_console.client_id'),
                'client_secret' => config('services.google_search_console.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            $connection->update([
                'status' => 'needs_reconnect',
                'error_message' => $response->json('error_description') ?? $response->json('error') ?? 'Google token refresh failed.',
            ]);

            throw new RuntimeException($connection->error_message ?? 'Google token refresh failed.');
        }

        $payload = $response->json();
        $connection->update([
            'access_token' => $payload['access_token'],
            'token_type' => $payload['token_type'] ?? $connection->token_type,
            'expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3600)),
            'status' => 'connected',
            'error_message' => null,
        ]);

        return $connection->fresh();
    }

    public function listSites(SearchConsoleConnection $connection): array
    {
        $connection = $this->refreshIfNeeded($connection);

        $response = $this->client($connection)->get($this->apiUrl('/sites'));

        if ($response->failed()) {
            $connection->update([
                'status' => 'failed',
                'error_message' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new RuntimeException($connection->error_message ?? 'Could not load Search Console sites.');
        }

        return $response->json('siteEntry', []);
    }

    public function syncProperties(SearchConsoleConnection $connection): Collection
    {
        $sites = $this->listSites($connection);
        $account = $connection->account()->firstOrFail();
        $stores = ShopifyStore::query()->forAccount($account)->get();
        $hasSelectedProperty = SearchConsoleProperty::query()
            ->forAccount($account)
            ->where('selected', true)
            ->exists();

        $properties = collect($sites)
            ->filter(fn (array $site): bool => filled($site['siteUrl'] ?? null))
            ->map(function (array $site) use ($connection, $account, $stores, &$hasSelectedProperty): SearchConsoleProperty {
                $store = $this->matchingStore($stores, (string) $site['siteUrl']);
                $existingProperty = SearchConsoleProperty::query()
                    ->where('account_id', $account->id)
                    ->where('site_url', $site['siteUrl'])
                    ->first();
                $selected = (bool) $existingProperty?->selected;

                if (! $hasSelectedProperty && $store) {
                    $selected = true;
                    $hasSelectedProperty = true;
                }

                return SearchConsoleProperty::query()->updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'site_url' => $site['siteUrl'],
                    ],
                    [
                        'search_console_connection_id' => $connection->id,
                        'shopify_store_id' => $store?->id,
                        'permission_level' => $site['permissionLevel'] ?? null,
                        'selected' => $selected,
                    ]
                );
            })
            ->values();

        if (! $properties->contains(fn (SearchConsoleProperty $property): bool => $property->selected) && $properties->isNotEmpty()) {
            $properties->first()->update(['selected' => true]);
        }

        $connection->update([
            'last_synced_at' => now(),
            'status' => 'connected',
            'error_message' => null,
        ]);

        return SearchConsoleProperty::query()
            ->forAccount($account)
            ->with('store:id,name,shop_domain,shop_url')
            ->orderByDesc('selected')
            ->orderBy('site_url')
            ->get();
    }

    public function seedTrackedKeywords(Account $account): int
    {
        $count = 0;

        Blog::query()
            ->forAccount($account)
            ->with('store:id,shop_url')
            ->whereNotNull('primary_keyword')
            ->get()
            ->each(function (Blog $blog) use (&$count): void {
                $keywords = collect([$blog->primary_keyword])
                    ->merge((array) $blog->secondary_keywords)
                    ->filter()
                    ->unique(fn (string $keyword): string => Str::lower(trim($keyword)));

                foreach ($keywords as $keyword) {
                    $trackedKeyword = $this->trackKeyword($blog->account_id, [
                        'shopify_store_id' => $blog->shopify_store_id,
                        'blog_id' => $blog->id,
                        'keyword' => $keyword,
                        'target_url' => $blog->published_url,
                        'source_type' => Blog::class,
                        'source_id' => $blog->id,
                    ]);

                    if ($trackedKeyword->wasRecentlyCreated) {
                        $count++;
                    }
                }
            });

        BlogTopic::query()
            ->forAccount($account)
            ->whereIn('status', ['generated', 'approved'])
            ->get()
            ->each(function (BlogTopic $topic) use (&$count): void {
                $keywords = collect([$topic->primary_keyword])
                    ->merge((array) $topic->secondary_keywords)
                    ->filter()
                    ->unique(fn (string $keyword): string => Str::lower(trim($keyword)));

                foreach ($keywords as $keyword) {
                    $trackedKeyword = $this->trackKeyword($topic->account_id, [
                        'shopify_store_id' => $topic->shopify_store_id,
                        'keyword' => $keyword,
                        'source_type' => BlogTopic::class,
                        'source_id' => $topic->id,
                        'intent' => $topic->search_intent,
                    ]);

                    if ($trackedKeyword->wasRecentlyCreated) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function syncSearchAnalytics(SearchConsoleProperty $property, CarbonInterface|string $startDate, CarbonInterface|string $endDate): int
    {
        $property->loadMissing('connection');
        $connection = $this->refreshIfNeeded($property->connection);
        $start = $startDate instanceof CarbonInterface ? $startDate->toDateString() : (string) $startDate;
        $end = $endDate instanceof CarbonInterface ? $endDate->toDateString() : (string) $endDate;

        $response = $this->client($connection)->post(
            $this->apiUrl('/sites/'.rawurlencode($property->site_url).'/searchAnalytics/query'),
            [
                'startDate' => $start,
                'endDate' => $end,
                'dimensions' => ['date', 'query', 'page', 'country', 'device'],
                'type' => 'web',
                'rowLimit' => 5000,
                'dataState' => 'final',
            ]
        );

        if ($response->failed()) {
            $connection->update([
                'status' => 'failed',
                'error_message' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new RuntimeException($connection->error_message ?? 'Search Console analytics sync failed.');
        }

        KeywordPositionSnapshot::query()
            ->where('account_id', $property->account_id)
            ->where('search_console_property_id', $property->id)
            ->whereBetween('date', [$start, $end])
            ->delete();

        $rows = $response->json('rows', []);
        $stored = 0;

        foreach ($rows as $row) {
            $keys = $row['keys'] ?? [];
            $date = $keys[0] ?? null;
            $query = $keys[1] ?? null;
            $page = $keys[2] ?? null;
            $country = $keys[3] ?? null;
            $device = $keys[4] ?? null;

            if (! $query) {
                continue;
            }

            $trackedKeyword = $this->trackKeyword($property->account_id, [
                'shopify_store_id' => $property->shopify_store_id,
                'keyword' => $query,
                'target_url' => $page,
                'source_type' => 'search_console',
                'source_id' => $property->id,
            ]);

            KeywordPositionSnapshot::query()->create([
                'account_id' => $property->account_id,
                'tracked_keyword_id' => $trackedKeyword->id,
                'shopify_store_id' => $property->shopify_store_id,
                'search_console_property_id' => $property->id,
                'source' => 'search_console',
                'date' => $date,
                'query' => Str::limit((string) $query, 255, ''),
                'page' => $page,
                'country' => $country,
                'device' => $device,
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => isset($row['position']) ? round((float) $row['position'], 2) : null,
                'metadata' => [
                    'response_aggregation_type' => $response->json('responseAggregationType'),
                ],
            ]);

            $trackedKeyword->update(['last_checked_at' => now()]);
            $stored++;
        }

        $property->update(['last_synced_at' => now()]);
        $connection->update([
            'last_synced_at' => now(),
            'status' => 'connected',
            'error_message' => null,
        ]);

        return $stored;
    }

    private function client(SearchConsoleConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('services.google_search_console.timeout', 45))
            ->withToken($connection->access_token);
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('services.google_search_console.api_url'), '/').$path;
    }

    private function fetchGoogleEmail(string $accessToken): ?string
    {
        $response = Http::acceptJson()
            ->timeout((int) config('services.google_search_console.timeout', 45))
            ->withToken($accessToken)
            ->get(config('services.google_search_console.userinfo_url'));

        if ($response->failed()) {
            return null;
        }

        return $response->json('email');
    }

    private function trackKeyword(int $accountId, array $attributes): TrackedKeyword
    {
        $keyword = Str::lower(trim((string) ($attributes['keyword'] ?? '')));

        if ($keyword === '') {
            throw new RuntimeException('Tracked keyword cannot be empty.');
        }

        return TrackedKeyword::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'keyword' => Str::limit($keyword, 255, ''),
                'target_url' => $attributes['target_url'] ?? null,
            ],
            [
                'shopify_store_id' => $attributes['shopify_store_id'] ?? null,
                'blog_id' => $attributes['blog_id'] ?? null,
                'source_type' => $attributes['source_type'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
                'intent' => $attributes['intent'] ?? null,
                'status' => 'active',
                'first_seen_at' => now(),
            ]
        );
    }

    private function matchingStore(Collection $stores, string $siteUrl): ?ShopifyStore
    {
        $siteDomain = $this->domainFromSiteUrl($siteUrl);

        if (! $siteDomain) {
            return null;
        }

        return $stores->first(function (ShopifyStore $store) use ($siteDomain): bool {
            $domains = collect([
                $store->shop_domain,
                parse_url((string) $store->shop_url, PHP_URL_HOST),
                Arr::get($store->metadata, 'primaryDomain.host'),
                parse_url((string) Arr::get($store->metadata, 'primaryDomain.url'), PHP_URL_HOST),
            ])
                ->filter()
                ->map(fn (string $domain): string => $this->stripWww($domain))
                ->unique();

            return $domains->contains(function (string $storeDomain) use ($siteDomain): bool {
                return $siteDomain === $storeDomain
                    || Str::endsWith($siteDomain, '.'.$storeDomain)
                    || Str::endsWith($storeDomain, '.'.$siteDomain);
            });
        });
    }

    private function domainFromSiteUrl(string $siteUrl): ?string
    {
        if (Str::startsWith($siteUrl, 'sc-domain:')) {
            return $this->stripWww(Str::after($siteUrl, 'sc-domain:'));
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);

        return $host ? $this->stripWww($host) : null;
    }

    private function stripWww(string $domain): string
    {
        return Str::of($domain)
            ->lower()
            ->replaceStart('www.', '')
            ->trim()
            ->toString();
    }
}
